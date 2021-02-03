<?php


namespace ArtisanWebworks\AutoCrud;

// Vendor
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use ReflectionException;
use Symfony\Component\HttpFoundation\Response;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

// Internal
include_once "relation-reflection.php";

/**
 * Class GenericAPIController
 *
 * For Eloquent models extending ValidatingModel, exposes generic CRUD API route
 * implementations, including validation and authorization.
 *
 * Sub-resource routes are inferred from Model relations.
 *
 * Built-in authorization confirms the target resource is associated with the logged in user,
 * according to rules defined in config('auto-crud.access-rules').
 *
 * Subclasses may bind to a specific model to specialize some controller behavior, while
 * still leveraging default logic.
 *
 * @package ArtisanWebworks\AutoCrud
 */
class GenericAPIController extends BaseController {
  use AuthorizesCrudOperations;

  /**
   * Specialized subclasses can bind to a specific model by defining this property.
   *
   * @var string Full classname of Eloquent ValidatingModel.
   */
  protected static string $modelType;

  /**
   * Subclasses may optionally specify a customized JsonResource implementation,
   * used when forming JSON responses.
   *
   * @var string Fully qualified classname of JsonResource.
   */
  protected static string $jsonResourceType;

  /**
   * Defines routes exposing generic CRUD for a ValidatingModel type.
   *
   * @param string|null $forModelType omitted when called against a subclass.
   *
   * @param array $options TODO: not implemented
   *
   *    'only': array of CRUD operations to expose, expressed as any combination of the following:
   *      'retrieve', 'retrieve-all', 'create', 'update', 'delete'
   *
   *    'recursion-depth': the number of sub-resources to be chained together in a route URI;
   *       overrides the general setting in 'auto-crud.recursion-depth' (see config comments).
   *
   * @throws ReflectionException
   */
  public static function declareRoutes(
    string $forModelType = null,
    array $options = []
  ) {
    $depth =
      $options['recursion-depth'] ?? config('auto-crud.recursion-depth', 10);
    $rootNode = ResourceNodeSchema::createRootResourceNode($forModelType);
    GenericAPIController::recursivelyDeclareRelationRoutes([$rootNode], $depth);
  }

  protected static function retrieveOne(
    ResourceNodeSchema $schema,
    int $id
  ): JsonResponse {
    return static::tryCRUD(
      function () use ($schema, $id) {

        $model = $schema->modelClass::find($id);
        if (!$model) {
          return self::invalidIdResponse($schema->modelClass, $id);
        }

        return static::jsonModelResponse(
          $model,
          Response::HTTP_OK
        );
      }
    );
  }

  protected static function retrieveAll(
    ResourceNodeSchema $schema,
    ?int $parentId
  ): JsonResponse {
    return static::tryCRUD(
      function () use ($schema, $parentId) {

        $all = null;
        if (!$schema->parent) {

          // If this is a root level resource, we access all the corresponding models
          // via the Model Facade class.
          $all = $schema->modelClass::all();
        }
        else {

          // If this resource is a sub-resource, we invoke the parent instance's
          // relationship property
          $all =
            $schema->parent->instantiateModel(
              $parentId
            )[$schema->relationMethodName];
        }

        return static::jsonModelResponse($all, Response::HTTP_OK);
      }
    );
  }

  /***
   * If $existingModelId specified, updates model, otherwise creates new one.
   * Extraneous request parameters will result in error.
   *
   * @param ResourceNodeSchema $schema
   * @param ValidatingModel|array $preview
   * @return JsonResponse - the updated/created json resource, or an error response
   */
  protected static function saveModelPreview(
    ResourceNodeSchema $schema,
    $preview
  ): JsonResponse {
    return static::tryCRUD(
      function () use ($schema, $preview) {
        try {

          // If post-create hooks throw an exception, roll back the operation.
          DB::transaction(function () use (&$preview) {

            // After saving the Model we must refresh() in order
            // to populate any 'with' relations.
            if (is_array($preview)) {
              foreach ($preview as $i => $instance) {
                $instance->save();
                $preview[$i] = $instance->fresh();
              }
            }
            else {
              $preview->save();
              $preview = $preview->fresh();
            }

          });

        } catch (ValidationException $e) {

          // Flatten the errors to one string per field instead
          // of an array of strings per field.
          $flattenedErrors = collect($e->errors())->mapWithKeys(
            function ($errorArray, $fieldName) {
              return [$fieldName => $errorArray[0]];
            }
          )->toArray();

          return static::badRequestResponse(
            $flattenedErrors,
            Response::HTTP_UNPROCESSABLE_ENTITY
          );
        }

        return static::jsonModelResponse($preview);
      }
    );
  }

  public static function delete(
    ResourceNodeSchema $schema,
    int $modelId
  ): JsonResponse {
    return static::tryCRUD(
      function () use ($schema, $modelId) {

        $model = $schema->modelClass::find($modelId);
        if (!$model) {
          return self::invalidIdResponse($schema->modelClass, $modelId);
        }
        $model->delete();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
      }
    );
  }

  /**
   * Provides error handling common to the various CRUD operations.
   *
   * @param Closure $crudOp
   * @return JsonResponse|mixed
   */
  protected static function tryCRUD(Closure $crudOp) {
    try {

      return $crudOp();

    } catch (Exception $e) {

      static::logCriticalError($e);
      return new JsonResponse(
        "server error",
        Response::HTTP_INTERNAL_SERVER_ERROR
      );

    }
  }

  /**
   * Subclass can override with custom error logging / alerting.
   *
   * @param Exception $e
   */
  protected static function logCriticalError($e) {
    Log::error($e);
  }

  /**
   * @param $errors array:  Array of field-name mapped to array of string messages.
   * @param $code int: HTTP response code (default is 400).
   */
  protected static function badRequestResponse(
    array $errors,
    $code = Response::HTTP_BAD_REQUEST
  ): JsonResponse {
    $responseData = ['errors' => $errors];
    return new JsonResponse($responseData, $code);
  }

  protected static function authorizationFailureResponse() {
    return static::badRequestResponse(
      ['client doesn\'t have access to resource'],
      Response::HTTP_FORBIDDEN
    );
  }

  protected static function unrecognizedFieldResponse(string $name
  ): JsonResponse {
    $responseData = ['errors' => [$name => "unrecognized field"]];
    return new JsonResponse($responseData, Response::HTTP_BAD_REQUEST);
  }

  /**
   * @param ValidatingModel|Collection<ValidatingModel> $modelData
   * @param int $httpCode
   * @return JsonResponse
   */
  protected static function jsonModelResponse(
    $modelData,
    int $httpCode = Response::HTTP_OK
  ): JsonResponse {

    JsonResource::$wrap = null;
    $jsonResourceType = static::$jsonResourceType ?? JsonResource::class;

    /** @var $jsonResource JsonResource */
    $jsonResource = ($modelData instanceof Collection || is_array($modelData)) ?
      $jsonResourceType::collection($modelData) :
      new $jsonResourceType($modelData);

    $response = $jsonResource->response();
    $response->setStatusCode($httpCode);
    $response->setEncodingOptions(
      JSON_NUMERIC_CHECK
    );  // numeric strings as numbers

    return $response;
  }

  private static function invalidIdResponse(
    string $modelType,
    int $id
  ) {
    // For security, we won't distinguish between non-existing and forbidden resources.
    return self::authorizationFailureResponse();
  }

  /**
   * Infer a set of resource routes by inspecting an Eloquent model and its relations.
   *
   * @param array<ResourceNodeSchema> $lineage - list of ResourceNodeSchema corresponding to a series of
   *   related resource as expressed along a REST resource URI path.
   * @param $maxDepth
   * @throws ReflectionException
   */
  protected static function recursivelyDeclareRelationRoutes(
    array $lineage,
    $maxDepth
  ) {

    /** @var ResourceNodeSchema $currentNode */
    $currentNode = end($lineage);

    static::declareImmediateRoutesForNode($currentNode);

    // We will only expose sub-resources to a specified depth
    if ($currentNode->depth === $maxDepth) {
      return;
    }

    // Iterate through public methods belonging to the current node's Eloquent model,
    // looking for relations that should be exposed as sub-resources.
    $relations = inspectRelations($currentNode->modelClass);
    foreach ($relations as $relationData) {

      // Push new resource schema onto lineage chain
      $lineage[] = ResourceNodeSchema::createSubResourceNode(
        $currentNode,
        $relationData
      );

      static::recursivelyDeclareRelationRoutes($lineage, $maxDepth);
    }
  }

  protected static function declareImmediateRoutesForNode(
    ResourceNodeSchema $schema
  ) {

    // The various CRUD operations are implemented by applying the
    // resource schema to a "stack" of one or more model id's,
    // representing a chain of related models; for example an operation
    // on 'users/i/posts/j/comments/k' is fulfilled by a schema for the
    // comments sub-resource, acting on id stack [i, j, k]

    static::declareCreateRoute($schema);
    static::declareBulkCreateRoute($schema);
    static::declareRetrieveOneRoute($schema);
    static::declareRetrieveManyRoute($schema);
    static::declareUpdateRoute($schema);
    static::declareDeleteRoute($schema);
  }

  protected static function declareCreateRoute(ResourceNodeSchema $schema) {
    Route::post(
      $schema->routeURIPrefix,
      function (Request $req, ...$uriIdStack) use ($schema) {
        static::castUriIdsToInt($uriIdStack);

        // Top of the URI id stack (if any), represents parent resource, so
        // we verify lineage relations starting with parent.
        if ($schema->parent && !$schema->parent->verifyLineage($uriIdStack)) {
          return static::authorizationFailureResponse();
        }

        // If a sub-resource route, automatically include in creation arguments
        // the foreign key field referencing the parent.
        $args = $req->all();
        if ($schema->parent) {
          $args[$schema->parentForeignKeyName] = end($uriIdStack);
        }

        // Instantiate (but don't yet save) the new model.
        $modelPreview = $schema->modelClass::make($args);

        if (
        !static::createOrUpdateIsAuthorized(
          $schema,
          Auth::id(),
          $uriIdStack,
          $modelPreview
        )
        ) {
          return static::authorizationFailureResponse();
        }

        return static::saveModelPreview($schema, $modelPreview);
      }
    )->name("{$schema->routeNamePrefix}." . Operation::CREATE);
  }

  protected static function declareBulkCreateRoute(ResourceNodeSchema $schema) {
    Route::post(
      $schema->routeURIPrefix . '-bulk',
      function (Request $req, ...$uriIdStack) use ($schema) {
        static::castUriIdsToInt($uriIdStack);

        // Top of the URI id stack (if any), represents parent resource, so
        // we verify lineage relations starting with parent.
        if ($schema->parent && !$schema->parent->verifyLineage($uriIdStack)) {
          return static::authorizationFailureResponse();
        }

        // If a sub-resource route, automatically include in creation arguments
        // the foreign key field referencing the parent.
        $argsSet = $req->all();
        if ($schema->parent) {
          foreach ($argsSet as $i => $args) {
            $argsSet[$i][$schema->parentForeignKeyName] = end($uriIdStack);
          }
        }

        // Instantiate (but don't yet save) the new model.
        $modelPreviews = array_map(
          function ($args) use ($schema) {
            return $schema->modelClass::make($args);
          },
          $argsSet
        );

        if (
        !static::createOrUpdateIsAuthorized(
          $schema,
          Auth::id(),
          $uriIdStack,
          $modelPreviews
        )
        ) {
          return static::authorizationFailureResponse();
        }

        return static::saveModelPreview($schema, $modelPreviews);
      }
    )->name("{$schema->routeNamePrefix}.bulk-" . Operation::CREATE);
  }

  protected static function declareRetrieveOneRoute(ResourceNodeSchema $schema
  ) {
    Route::get(
      $schema->routeURIPrefix . '/{' . $schema->uriIdName . '}',
      function (...$uriIdStack) use ($schema) {

        if (!$schema->verifyLineage($uriIdStack)) {
          return static::authorizationFailureResponse();
        }

        if (
        !static::retrieveOneIsAuthorized(
          $schema,
          Auth::id(),
          $uriIdStack
        )
        ) {
          return static::authorizationFailureResponse();
        }

        $id = end($uriIdStack);
        return static::retrieveOne($schema, $id);
      }
    )->name("{$schema->routeNamePrefix}." . Operation::RETRIEVE);
  }

  protected static function declareRetrieveManyRoute(ResourceNodeSchema $schema
  ) {
    Route::get(
      $schema->routeURIPrefix,
      function (...$uriIdStack) use ($schema) {

        // Top of the URI id stack (if any), represents parent resource, so
        // we verify lineage relations starting with parent.
        if ($schema->parent && !$schema->parent->verifyLineage($uriIdStack)) {
          return static::authorizationFailureResponse();
        }

        if (
        !static::retrieveManyIsAuthorized(
          $schema,
          Auth::id(),
          $uriIdStack
        )
        ) {
          return static::authorizationFailureResponse();
        }

        return static::retrieveAll($schema, end($uriIdStack));
      }
    )->name("{$schema->routeNamePrefix}." . Operation::RETRIEVE_ALL);
  }

  protected static function declareUpdateRoute(ResourceNodeSchema $schema) {
    Route::patch(
      $schema->routeURIPrefix . '/{' . $schema->uriIdName . '}',
      function (Request $req, ...$uriIdStack) use ($schema) {

        if (!$schema->verifyLineage($uriIdStack)) {
          return static::authorizationFailureResponse();
        }

        $id = array_pop($uriIdStack);
        $previewModel = $schema->modelClass::find($id);
        if (!$previewModel) {
          return static::authorizationFailureResponse();
        }
        $previewModel->fill($req->all());

        if (
        !static::createOrUpdateIsAuthorized(
          $schema,
          Auth::id(),
          $uriIdStack,
          $previewModel
        )
        ) {
          return static::authorizationFailureResponse();
        }

        return static::saveModelPreview($schema, $previewModel);
      }
    )->name("{$schema->routeNamePrefix}." . Operation::UPDATE);
  }

  protected static function declareDeleteRoute(ResourceNodeSchema $schema) {
    Route::delete(
      $schema->routeURIPrefix . '/{' . $schema->uriIdName . '}',
      function (...$uriIdStack) use ($schema) {

        if (!$schema->verifyLineage($uriIdStack)) {
          return static::authorizationFailureResponse();
        }

        if (
        !static::deleteIsAuthorized(
          $schema,
          Auth::id(),
          $uriIdStack
        )
        ) {
          return static::authorizationFailureResponse();
        }

        $id = end($uriIdStack);
        return static::delete($schema, $id);
      }
    )->name("{$schema->routeNamePrefix}." . Operation::DELETE);
  }

  protected static function castUriIdsToInt(array &$ids) {
    array_walk(
      $ids,
      function (&$id) {
        if (is_numeric($id)) {
          $id = (int)$id;
        }
      }
    );
  }

}


