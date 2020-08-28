<?php


namespace ArtisanWebworks\AutoCRUD;

// Vendor
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Closure;
use Exception;

abstract class BaseAPIController extends BaseController {

  /**
   * @var string Fully qualified classname of ValidatingModel subclass.
   */
  protected static string $modelType;

  protected static JsonResource $resourceType;


  /**
   * Derives url fragments and Controller that serves requests from the Model
   * class name specified in $modelType class member;
   * \App\Foo will generate "foo/" and "foos/" REST url's,
   * served by FooApiController.
   */
  public static function declareRoutes() {
    $modelName = last(explode('\\', static::$modelType));
    $nameLower = strtolower($modelName);
    $controller = "API\\" . $modelName . 'APIController';
    Route::post("/{$nameLower}s", "{$controller}@updateOrCreate")
      ->name("api.{$nameLower}.create");
    Route::get("/{$nameLower}s/{id}", "{$controller}@get")
      ->name("api.{$nameLower}.get");
    Route::get("/{$nameLower}s", "{$controller}@getAll")
      ->name("api.{$nameLower}s.get");
    Route::put("/{$nameLower}s/{id}", "{$controller}@updateOrCreate")
      ->name("api.{$nameLower}.update");
    Route::delete("/{$nameLower}s/{id}", "{$controller}@delete")
      ->name("api.{$nameLower}.delete");
  }

  public function get(int $modelId): JsonResponse {
    return $this->tryCRUD(
      function () use ($modelId) {
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->jsonModelResponse(static::$modelType::find($modelId), Response::HTTP_OK);
      }
    );
  }

  public function getAll(): JsonResponse {
    return $this->tryCRUD(
      function () {
        $allModels = static::$modelType::all();
        /** @var $collection Collection */
        $jsonResource = static::$resourceType::collection($allModels);
        $jsonResource::$wrap = null;
        $response = $jsonResource->response();
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
      }
    );
  }

  /***
   * If $existingModelId specified, updates model, otherwise creates.
   * Extraneous request parameters will result in error.
   *
   * @param Request $req
   * @param int|null $existingModelId
   * @return JsonResponse
   */
  public function updateOrCreate(Request $req, int $existingModelId = null): JsonResponse {
    return $this->tryCRUD(
      function () use ($req, $existingModelId) {
        $isUpdate = $existingModelId !== null;

        // Reject request if it includes an unrecognized field
        foreach ($req->all() as $key => $value) {
          if (!static::$modelInstance->isFillable()) {
            //  TODO: throw error
          }
        }

        /** @noinspection PhpUndefinedMethodInspection */
        $existingModel = $existingModelId ? static::$modelType::find($existingModelId) : null;

        // Validate model members and relations in one go.
        $data = $req->only(static::$modelType::allFillable());
        $errors = static::$modelType::validate($data, $existingModel);

        if (count($errors) > 0) {
          return $this->badRequestResponse($errors);
        }

        // Create instance, or fetch existing for update.
        $entity = null;
        if (!$isUpdate) {
          $this->preCreate($data);
          $entity = new static::$modelType($data);
          /** @var $entity ValidatingModel */
          $entity->save();

          // Default vales aren't reflected correctly until we refresh
          // https://github.com/laravel/framework/issues/21449
          $entity->refresh();
        }
        else {
          $this->preUpdate($data, $existingModel);
          $entity = $existingModel;
          /** @noinspection PhpUndefinedMethodInspection */
          $entity->update($data);
        }

        // Establish entity relationships per passed in arguments.
        foreach (static::$modelType::RELATION_FILLABLE as $relationKey) {
          if (array_key_exists($relationKey, $data)) {
            static::$modelType::createRelation($relationKey, $data[$relationKey], $entity);
          }
        }

        // Handle "special" fillable arguments; for example, download or save the URL or file
        // associated with an Image request.
        foreach (static::$modelType::SPECIAL_FILLABLE as $specialKey) {
          if (array_key_exists($specialKey, $data)) {
            static::$modelType::handleSpecial($specialKey, $data[$specialKey], $entity);
          }
        }

        return $this->jsonModelResponse(
          $entity,
          $isUpdate ? Response::HTTP_ACCEPTED : Response::HTTP_CREATED
        );
      }
    );
  }

  /**
   * Update and Create hooks
   */
  protected function preCreate($data) {
  }

  protected function preUpdate($data, $existingModel) {
  }

  public function delete(int $modelId): JsonResponse {
    return $this->tryCRUD(
      function () use ($modelId) {
        /** @noinspection PhpUndefinedMethodInspection */
        $model = static::$modelType::find($modelId);
        /** @var $model ValidatingModel */
        if ($model) {
          $model->delete();
        }
        return JsonResponse::create("deleted", Response::HTTP_NO_CONTENT);
      }
    );
  }


  // ---------- PRIVATE HELPERS ---------- //

  protected function tryCRUD(Closure $crudOp) {
    try {
      return $crudOp();
    } catch (Exception $e) {
      Log::error($e);
      return new JsonResponse(
        "server error",
        Response::HTTP_INTERNAL_SERVER_ERROR
      );
    }
  }

  /**
   * @param $errors array:  Array of field-name mapped to array of string messages.
   */
  protected function badRequestResponse(array $errors): JsonResponse {
    $responseData = ['errors' => $errors];
    return new JsonResponse($responseData, Response::HTTP_BAD_REQUEST);
  }

  protected function unrecognizedFieldResponse(string $name): JsonResponse {
    $responseData = ['errors' => [$name => "unrecognized field"]];
    return new JsonResponse($responseData, Response::HTTP_BAD_REQUEST);
  }

  protected function jsonModelResponse(ValidatingModel $model, int $httpCode): JsonResponse {
    $jsonResource = new static::$resourceType($model);
    /** @var $jsonResource JsonResource */
    $response = $jsonResource->response();
    /** @noinspection PhpParamsInspection */
    $response->setStatusCode($httpCode);
    $response->setEncodingOptions(JSON_NUMERIC_CHECK);  // numeric strings as numbers
    return $response;
  }

  private static function getEmptyModelInstance() {
    static $model = null;
    if (!$model) {
      $model = new static::$modelType();
    }
    return $model;
  }

}


