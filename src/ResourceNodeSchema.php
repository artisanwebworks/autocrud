<?php


namespace ArtisanWebworks\AutoCRUD;

use function PHPUnit\Framework\isFalse;
use Illuminate\Support\Facades\DB;

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
  public ?string $relationMethodName;

  /**
   * @var string|null - the field identifier on the target resource referencing the parent
   */
  public ?string $relationForeignKeyName;

  /**
   * @param string $modelType
   * @param ResourceNodeSchema|null $parent,
   * @param bool $hasSiblings
   * @param string|null $relationMethodName
   * @param string|null $relationForeignKeyName
   */
  public function __construct(
    string $modelType,
    ?ResourceNodeSchema $parent,
    bool $hasSiblings,
    string $relationMethodName = null,
    string $relationForeignKeyName = null
  ) {
    $this->modelType = $modelType;
    $this->parent = $parent;
    $this->hasSiblings = $hasSiblings;

    // Name is explicitly passed, derived from the Eloquent relation identifier,
    // or derived from the Model class name, attempted in that order.
    $this->name =$relationMethodName ?
        strtolower($relationMethodName) :
        static::deriveNameFromModelClass($modelType, $hasSiblings);

    $this->relationMethodName = $relationMethodName;
    $this->relationForeignKeyName = $relationForeignKeyName;

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

  /**
   * Given a stack of resource ids [i, j, k, ...], corresponding to
   * a REST API endpoint URI parameters, for example, /users/i/posts/j/comments/k...,
   * verify the relations implied by the path actually exist (eg, there is User i,
   * with Post j, having Comment k).
   *
   * @param array $uriIdStack - stack of ids, with one for this resource schema type
   *   and one for each ancestor node
   * @returns bool - true if the relations expressed by the URI path are valid
   */
  protected function verifyLineage(array $uriIdStack) {
    for ($node = $this; $node; $node = $node->parent) {

      // If no further relations to examine, verification succeeded
      if (!$node->parent) {
        return true;
      }

      // There is one or more parent-child relationship left to examine, so there
      // should be at least two ids left in the stack (the child, then parent).
      if (count($uriIdStack) < 2) {
        return false;
      }

      // Query DB to confirm the parent-child relationship exists
      $childId = array_pop($uriIdStack);
      $parentId = end($uriIdStack);
      $result = DB::select("\
        select * from {$node->table} \ 
        where {$node->idColumnName} = ? \
        and where {$node->parent->relationForeignKeyName} = ?",
        [$childId, $parentId]
      );
      if (!$result) {
        return false;
      }
    }
  }

}