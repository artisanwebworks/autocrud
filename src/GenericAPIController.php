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
use phpDocumentor\Reflection\Types\True_;
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
    $rootNode = ResourcePathNodeSchema::createRootResourceNode($forModelType);
    GenericAPIController::recursivelyDeclareRelationRoutes($rootNode, $depth);
  }

  protected static function retrieveOne(
    ResourcePathNodeSchema $schema,
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
    ResourcePathNodeSchema $schema,
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
   * @param ResourcePathNodeSchema $schema
   * @param ValidatingModel|array $preview
   * @return JsonResponse - the updated/created json resource, or an error response
   */
  protected static function saveModelPreview(
    ResourcePathNodeSchema $schema,
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
    ResourcePathNodeSchema $schema,
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
   * @param ResourcePathNodeSchema $head - the end of a chain of resource schemas
   * @param $maxDepth
   * @throws ReflectionException
   */
  protected static function recursivelyDeclareRelationRoutes(
    ResourcePathNodeSchema $head,
    $maxDepth
  ) {

    static::declareImmediateRoutesForNode($head);

    if ($head->depth === $maxDepth) {
      return;
    }

    $relations = enumerateRelations($head->modelClass);

    // Create a new branch path for every relation
    foreach ($relations as $relationData) {

      $branch = ResourcePathNodeSchema::createSubResourceNode(
        $head,
        $relationData
      );

      static::recursivelyDeclareRelationRoutes($branch, $maxDepth);
    }
  }

  protected static function declareImmediateRoutesForNode(
    ResourcePathNodeSchema $schema
  ) {

    // The various CRUD operations are implemented by applying the
    // resource schema to a "stack" of one or more model id's,
    // representing a chain of related models; for example an operation
    // on 'users/i/posts/j/comments/k' is fulfilled by a schema for the
    // comments sub-resource, acting on id stack [i, j, k]

    if ($schema->parent) {

      // Creation and deletion limited to sub-resources
      static::declareCreateRoute($schema);
      static::declareDeleteRoute($schema);
      if ($schema->cardinality === "many") {
        static::declareBulkCreateRoute($schema);
        static::declareRetrieveManyRoute($schema);
      }
    }

    static::declareRetrieveOneRoute($schema);
    static::declareUpdateRoute($schema);

  }

  /**
   * Return a Closure which performs the action for an CRUD route. Wraps CRUD
   * logic in validation and access control, as well as resolving the endpoint
   * to a specific resource, in the form of an Eloquent Model id.
   *
   * @param $schema
   * @param $crudLogic -- callback that performs CRUD logic and returns a response
   * @param bool $isInContextOfExistingInstance -- false for create, and retrieve-all contexts
   * @return Closure -- returns a closure, which when invoked performs generic API
   *  endpoint access control and resolution, then invokes the $crudLogic callback,
   *  and relays its response result.
   */
  protected static function declareRouteAction(
    $schema,
    $crudLogic,
    $isInContextOfExistingInstance = true
  ): Closure {

    return function (Request $req, ...$uriIdStack) use (
      $schema,
      $crudLogic,
      $isInContextOfExistingInstance
    ) {

      static::castUriIdsToInt($uriIdStack);

      try {
        $endpointResourceId =
          self::resolvePathEndpointInstance(
            $uriIdStack,
            $schema,
            $isInContextOfExistingInstance
          );
      } catch (Exception $e) {
        return static::authorizationFailureResponse();
      }

       $args = [
         'request-data' => $req->all(),
         'endpoint-resource-id' => $endpointResourceId
       ];

       return $crudLogic($schema, $args);
    };

  }

  protected static function declareCreateRoute(ResourcePathNodeSchema $schema) {

    $createLogic = function ($schema, $crudLogicArgs) {

      // If a sub-resource route, automatically include in creation arguments
      // the foreign key field referencing the parent.
      $createArgs = $crudLogicArgs['request-data'];
      if ($schema->parent) {
        $parentId = $crudLogicArgs['endpoint-resource-id'];
        $createArgs[$schema->parentForeignKeyName] = $parentId;
      }

      // Instantiate (but don't yet save) the new model.
      $modelPreview = $schema->modelClass::make($createArgs);

      return static::saveModelPreview($schema, $modelPreview);
    };

    Route::post(
      $schema->generateRouteUrl(false),
      self::declareRouteAction($schema, $createLogic, false)
    )->name("{$schema->routeNamePrefix}." . Operation::CREATE);

  }

  protected static function declareBulkCreateRoute(ResourcePathNodeSchema $schema) {

    $bulkCreateLogic = function ($schema, $crudLogicArgs) {

      // If a sub-resource route, automatically include in creation arguments
      // the foreign key field referencing the parent.
      $argsSet = $crudLogicArgs['request-data'];
      if ($schema->parent) {
        foreach ($argsSet as $i => $args) {
          $argsSet[$i][$schema->parentForeignKeyName] =
            $crudLogicArgs['endpoint-resource-id'];
        }
      }

      // Instantiate (but don't yet save) the new model.
      $modelPreviews = array_map(
        function ($args) use ($schema) {
          return $schema->modelClass::make($args);
        },
        $argsSet
      );

      return static::saveModelPreview($schema, $modelPreviews);
    };

    Route::post(
      $schema->generateRouteUrl(false) . '-bulk',
      self::declareRouteAction($schema, $bulkCreateLogic, false)
    )->name("{$schema->routeNamePrefix}.bulk-" . Operation::CREATE);
  }

  protected static function declareRetrieveOneRoute(ResourcePathNodeSchema $schema
  ) {
    $retrieveOneLogic = function ($schema, $crudLogicArgs) {
      return static::retrieveOne($schema, $crudLogicArgs['endpoint-resource-id']);
    };

    Route::get(
      $schema->generateRouteUrl(true),
      self::declareRouteAction($schema, $retrieveOneLogic)
    )->name("{$schema->routeNamePrefix}." . Operation::RETRIEVE);
  }

  protected static function declareRetrieveManyRoute(ResourcePathNodeSchema $schema
  ) {
    $retrieveManyLogic = function ($schema, $crudLogicArgs) {
      return static::retrieveAll(
        $schema,
        $crudLogicArgs['endpoint-resource-id'],
      );
    };

    Route::get(
      $schema->generateRouteUrl(false),
      self::declareRouteAction($schema, $retrieveManyLogic, false)
    )->name("{$schema->routeNamePrefix}." . Operation::RETRIEVE_ALL);
  }

  protected static function declareUpdateRoute(ResourcePathNodeSchema $schema) {

    $updateLogic = function ($schema, $crudLogicArgs) {
      $previewModel = $schema->modelClass::find(
        $crudLogicArgs['endpoint-resource-id']
      );
      $previewModel->fill($crudLogicArgs['request-data']);
      return static::saveModelPreview($schema, $previewModel);
    };

    Route::patch(
      $schema->generateRouteUrl(true),
      self::declareRouteAction($schema, $updateLogic)
    )->name("{$schema->routeNamePrefix}." . Operation::UPDATE);
  }

  protected static function declareDeleteRoute(ResourcePathNodeSchema $schema) {

    $deleteLogic = function ($schema, $crudLogicArgs) {
      return static::delete($schema, $crudLogicArgs['endpoint-resource-id']);
    };

    Route::delete(
      $schema->generateRouteUrl(true),
      self::declareRouteAction($schema, $deleteLogic)
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

  /**
   * Given a a stack of id stack, resolve the end-most defined resource instance in a chain of
   * sub-resources stemming from a root resource.
   *
   * For certain resolution contexts (namely creation, and mass retrieval), the id stack does
   * not identify an instance for end-most resource schema, in which case the end-most
   * *defined* resource is an instance of the parent-schema, second from end.
   *
   * Throws if...
   *
   *  1. Auth user is not connected to the root resource instance.
   *  2. The relations implied by the $uriIdStack do not exist.
   *
   * @param array $uriIdStack
   *
   * @param ResourcePathNodeSchema $pathEndpoint - the end-most resource schema node
   *
   * @param bool $isInContextOfExistingInstance false if endpoint is called in the context of
   *  resource creation, or mass retrieval, meaning there is no id on the top of the stack representing
   *  a specific resource instance.
   *
   * @return int id of the end-most defined instance; in the cases of create and retrieve-many,
   *  corresponding to parent schema, in all other cases, corresponding to the endpoint schema.
   *
   * @throws ResourceNotFoundException
   * @throws ResourceAccessDeniedException
   */
  protected static function resolvePathEndpointInstance(
    array $uriIdStack,
    ResourcePathNodeSchema $pathEndpoint,
    bool $isInContextOfExistingInstance = true
  ): int {

    // Verify access to path root
    $rootSchema = $pathEndpoint->getRoot();

    // Pop the root id from front of stack.
    $rootId = array_shift($uriIdStack);

    if (!self::authUserCanAccess($rootSchema, $rootId)) {
      throw new ResourceAccessDeniedException();
    }

    // Traverse parent-child relations starting from path root.
    $parentId = $rootId;
    for (
      $parent = $rootSchema;
      $parent->child;
      $parent = $parent->child
    ) {

      $child = $parent->child;

      if ($child->cardinality === "many") {

        // When foo has a "has many" relation to bar, the resource path URI includes a bar id;
        // eg, '/foos/1/bars/22',  yielding $uriIdStack [1, 22], except
        // in the context of creation, where the endpoint id is undefined

        if (empty($uriIdStack)) {

          // We only accept an undefined path node if it is the end node in the
          // context of creation
          if ($isInContextOfExistingInstance || $child->child) {
            throw new ResourceNotFoundException();
          }

        } else {

          // When node is defined by an id, check that it is indeed a valid
          // relation to parent.

          $childId = array_shift($uriIdStack);

          $records = DB::table($child->table)
            ->where('id', '=', $childId)
            ->where($child->parentForeignKeyName, '=', $parentId)
            ->get();

          if ($records->count() !== 1) {
            throw new ResourceNotFoundException();
          }

          $parentId = $childId;
        }


      } else {

        // When foo  has a "has one" relation to bar, no bar id is specified in path,
        // eg, '/foos/1/bar', yields $uriIdStack [1]
        // In this case, there should be exactly one record in child table referencing
        // parent, hence we need not supply an id to find it.

        if ($isInContextOfExistingInstance || $child->child /** not end-most */) {

          // We must verify the has one relation,
          $childRecord = DB::table($child->table)
            ->where($child->parentForeignKeyName, '=', $parentId)
            ->get()
            ->first();
          if (!$childRecord) {
            throw new ResourceNotFoundException();
          }
          $parentId = (int)$childRecord->id;
        }

      }

    }

    // Return id of last defined path-node resource, which within create context,
    // is the parent resource, and in all other contexts, the endpoint resource
    return $parentId;

  }

  /**
   * Returns true if the
   *
   * @param ResourcePathNodeSchema $node
   * @param int $id
   * @return bool
   */
  protected static function authUserCanAccess(
    ResourcePathNodeSchema $node,
    int $id
  ): bool {

    $rawRecord = DB::table($node->table)
      ->where('id', $id)
      ->get()
      ->first();

    foreach (config('auto-crud.access-rules') as $rule) {

      // Don't need to check rules that specify a non-matching model condition.
      if (isset($rule['model']) && $rule['model'] !== $node->modelClass) {
        continue;
      }

      $userIdPropName = $rule['user-id-property'];
      if (Auth::id() == $rawRecord->$userIdPropName) {
        return true;
      }

    }

    return false;
  }
}


