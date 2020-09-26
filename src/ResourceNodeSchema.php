<?php


namespace ArtisanWebworks\AutoCRUD;


use Illuminate\Auth\Access\AuthorizationException;
use function PHPUnit\Framework\isFalse;

/**
 * Class ResourceNodeSchema
 *
 * Describes an abstract API resource and corresponding Eloquent model type, optionally
 * as a sub-resource belonging to a chain of 1 or more parent resources.
 *
 * @package ArtisanWebworks\AutoCRUD
 */
class ResourceNodeSchema {

  /**
   * @var string - the resource we are describing corresponds to this Eloquent class.
   */
  public string $modelType;

  /**
   * @var ResourceNodeSchema|null - the node describing the parent resource (if any).
   */
  public ?ResourceNodeSchema $parent;

  /**
   * @var bool - determines if we express URI and route names in the singular or plural.
   */
  public bool $hasSiblings;

  /**
   * @var string - the resource's name as included URI and route names.
   *
   * !! must be a valid URI directory name !!
   */
  public string $name;

  /**
   * @var string - the name of the id URI parameter, for example 'foos/{foo}',
   * corresponds to $idName set to 'foo'.
   */
  public string $idName;

  /**
   * @var string - the base of the Laravel route name to which the crud operation strings
   *   will be appended.
   */
  public string $routeNamePrefix;

  /**
   * @var string - the base of the route URI to which crud operation strings will be appended.
   */
  public string $routeURIPrefix;

  /**
   * @var int - the number of ancestor resources
   */
  public int $depth;


  /**
   * @var string|null - if this is a sub resource, the identifier of the relation on the parent
   *   Eloquent Model.
   */
  public ?string $relationIdentifier;

  /**
   * @param string $modelType
   * @param ResourceNodeSchema|null $parent,
   * @param bool $hasSiblings
   * @param string|null $name - overrides the default name derived from model type.
   * @param string|null $relationIdentifier - the identifier invoked on the parent resource's model
   *   to yield this sub-resource.
   */
  public function __construct(
    string $modelType,
    ?ResourceNodeSchema $parent,
    bool $hasSiblings,
    string $name = null,
    string $relationIdentifier = null
  ) {
    $this->modelType = $modelType;
    $this->parent = $parent;
    $this->hasSiblings = $hasSiblings;

    // Name is explicitly passed, derived from the Eloquent relation identifier,
    // or derived from the Model class name, attempted in that order.
    $this->name = $name ??
      ($relationIdentifier ?
        strtolower($relationIdentifier) :
        static::deriveNameFromModelClass($modelType, $hasSiblings)
      );
    $this->relationIdentifier = $relationIdentifier;

    // The id name is the singular form of the model name.
    $this->idName = static::deriveNameFromModelClass($modelType, false);

    list($this->routeNamePrefix, $this->routeURIPrefix) = $this->generateRouteParameters();
    $this->depth = $parent ? $parent->depth + 1 : 0;
  }

  /**
   * Traverse ancestor nodes to form route name and URI.
   */
  public function generateRouteParameters() {
    $parent = $this->parent;
    $routeName = $this->name;
    $uri = $this->name;
    while ($parent) {
      $routeName = $parent->name . "." . $routeName;
      $uri = $parent->name . '/{' . $parent->idName . '}/' . $uri;
      $parent = $parent->parent;
    }
    $routeName = "api." . $routeName;
    $uri = "api/" . $uri;
    return [$routeName, $uri];
  }

  private static function deriveNameFromModelClass($modelType, $plural) {
    $modelName = last(explode('\\', $modelType));
    if ($plural) {
      $modelName .= 's';
    }
    return strtolower($modelName);
  }

  public function instantiateModel($id) {
    return $this->modelType::find($id);
  }

}