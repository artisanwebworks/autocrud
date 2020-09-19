<?php


namespace ArtisanWebworks\AutoCRUD;

// Vendor
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
 * @package ArtisanWebworks\AutoCRUD
 */
class GenericAPIController extends BaseController {

  /**
   *
   */
  protected const DEFAULT_MAX_DEPTH = 1;

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
   * @param array $options TODO: implement these
   *
   *    'route-prefix': changes prefix used in route URI and name from default 'api'
   *
   *    'only': array of CRUD operations to expose, expressed as any combination of the following:
   *            'retrieve', 'retrieve-all', 'create', 'update', 'delete'
   *
   *    'max-depth': the number of sub-resources to be chained together in a route URI
   * @throws \ReflectionException
   */
  public static function declareRoutes(
    string $forModelType = null,
    array $options = []
  ) {
    $rootNode = new ResourceNode($forModelType, null, true);
    GenericAPIController::recursivelyDeclareRelationRoutes([$rootNode], 1);
  }

  protected static function retrieve(
    string $modelType,
    int $modelId
  ): JsonResponse {
    return static::tryCRUD(
      function () use ($modelType, $modelId) {

        /** @noinspection PhpUndefinedMethodInspection */
        $model = $modelType::find($modelId);

        if (!$model) {
          return self::invalidIdResponse($modelType, $modelId);
        }

        return static::jsonModelResponse(
          $model,
          Response::HTTP_OK
        );
      }
    );
  }

  protected static function retrieveAll(ResourceNode $node): JsonResponse {
    return static::tryCRUD(
      function () use ($node) {

        $all = null;
        if (!$node->parent) {
          $allModels = $node->modelType::all();
        }

        return static::jsonModelResponse($allModels, Response::HTTP_OK);
      }
    );
  }

  /***
   * If $existingModelId specified, updates model, otherwise creates new one.
   * Extraneous request parameters will result in error.
   *
   * @param Request $req
   * @param string $modelType : fully qualified class name of a ValidatingModel corresponding to resource
   * @param int|null $existingModelId : operation inferred to be update if defined
   * @return JsonResponse: the updated/created json resource, or an error response
   */
  protected static function updateOrCreate(
    Request $req,
    string $modelType,
    int $existingModelId = null
  ): JsonResponse {
    return static::tryCRUD(
      function () use ($req, $modelType, $existingModelId) {

        // Reject request if it includes an unrecognized or non-fillable field
        $modelInstance = new $modelType;
        /** @var ValidatingModel $modelInstance */
        foreach ($req->keys() as $propertyName) {
          if (!$modelInstance->isFillable($propertyName)) {
            return static::badRequestResponse(
              ["$propertyName is an unrecognized field"]
            );
          }
        }

        try {
          $targetInstance = null;
          $isUpdate = $existingModelId !== null;
          if ($isUpdate) {
            /** @var ValidatingModel $targetInstance */
            $targetInstance = $modelType::find($existingModelId);
            if (!$targetInstance) {
              return static::invalidIdResponse($modelType, $existingModelId);
            }
            $targetInstance->update($req->all());
          }
          else {
            $targetInstance = $modelType::create($req->all());
          }
        } catch (ValidationException $e) {

          // Flatten the errors to one string per field instead
          // of an array of strings per field.
          $flattenedErrors = collect($e->errors())->mapWithKeys(
            function ($errorArray, $fieldName) {
              return [$fieldName => $errorArray[0]];
            }
          )->toArray();

          return static::badRequestResponse($flattenedErrors, 422);
        }

        return static::jsonModelResponse(
          $targetInstance,
          $isUpdate ? Response::HTTP_OK : Response::HTTP_CREATED
        );
      }
    );
  }

  public static function delete($modelType, int $modelId): JsonResponse {
    return static::tryCRUD(
      function () use ($modelType, $modelId) {
        /** @noinspection PhpUndefinedMethodInspection */
        $model = $modelType::find($modelId);

        if (!$model) {
          return self::invalidIdResponse($modelType, $modelId);
        }

        $model->delete();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
      }
    );
  }


  // ---------- PRIVATE HELPERS ---------- //

  /**
   * Provides error handling common to the various CRUD operations.
   *
   * @param Closure $crudOp
   * @return JsonResponse|mixed
   */
  protected static function tryCRUD(Closure $crudOp) {
    try {

      return $crudOp();

    }
    catch
    catch (Exception $e) {

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
    int $httpCode
  ): JsonResponse {

    $jsonResourceType = static::$jsonResourceType ?? JsonResource::class;

    /** @var $jsonResource JsonResource */
    $jsonResource = ($modelData instanceof Collection) ?
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
    $shortClassName = last(explode('\\', $modelType));
    $resourceName = strtolower($shortClassName);
    return static::badRequestResponse(
      ["$id is not a valid $resourceName id"],
      Response::HTTP_NOT_FOUND
    );
  }


  /**
   * Define resour
   *
   * @param array<ResourceNode> $lineage : list of RelationLineageNode, which correspond
   *   to
   * @param $maxDepth
   * @throws \ReflectionException
   */
  public static function recursivelyDeclareRelationRoutes(
    array $lineage,
    $maxDepth
  ) {

    /** @var ResourceNode $currentNode */
    $currentNode = end($lineage);

    echo " Declaring for resource $currentNode->routeName: $currentNode->routeURI\n";
    static::declareImmediateRoutesForNode($currentNode);

    if($currentNode->depth === $maxDepth) {
      return;
    }

    // Iterate through public methods belonging to the current node's Eloquent model
    $class = new \ReflectionClass($currentNode->modelType);
    foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {

      $returnType = $method->getReturnType();
      if (!$returnType) {
        continue;
      }

      // For each "has" relation, recursively declare routes
      $isHasMany = $returnType->getName() === HasMany::class;
      $isHasOne = $returnType->getName() === HasOne::class;
      if ($isHasOne || $isHasMany) {
        $relationMethodName = $method->getName();
        $blankEntity = new $currentNode->modelType();
        $relationTargetType = get_class($blankEntity->$relationMethodName()->getRelated());
        $lineage[] = new ResourceNode($relationTargetType, $currentNode, $isHasMany);
        static::recursivelyDeclareRelationRoutes($lineage, $maxDepth);
      }
    }

  }

  private static function declareImmediateRoutesForNode(ResourceNode $node) {

    // Retrieve all
    echo "\t retrieve all: GET $node->routeURI \n";
    Route::get(
      $node->routeURI,
      function (...$resourceIdStack) use ($node) {
        $node->authorize(Operation::RETRIEVE_ALL, Auth::id(), $resourceIdStack);
        return static::retrieveAll($node);
      }
    )->name("$node->routeName.retrieve-all");

  }

}


