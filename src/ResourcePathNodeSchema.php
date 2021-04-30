<?php


namespace ArtisanWebworks\AutoCrud;

use Exception;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Support\Facades\DB;

/**
 * Class ResourcePathNodeSchema
 *
 * Describes a single node in a doubly linked list representing a resource path
 * schema; an abstract representation of resources and sub-resources, without
 * respect to specific resource instances.
 *
 * Used to define API route endpoints.
 *
 * List nodes are traversed via $parent, $child properties.
 *
 * @package ArtisanWebworks\AutoCrud
 */
class ResourcePathNodeSchema {

  /**
   * @var string - the Eloquent class representing this resource.
   */
  public string $modelClass;

  /**
   * @var string - the database table representing this resource.
   */
  public string $table;

  /**
   * @var string - the primary key for DB records representing this resource.
   */
  public string $primaryKeyName;

  /**
   * @var string - the resource's name as included in URI and Laravel route names.
   */
  public string $name;

  /**
   * @var string - the resource's URI id-parameter name, for example 'foos/{foo}',
   * corresponds to $idName of 'foo'.
   */
  public string $uriIdName;

  /**
   * @var string - prefix for Laravel route names pertaining to this resource; for
   * example, for a route named 'api.foomodel.create', the route name prefix is
   * 'api.foomodels'.
   */
  public string $routeNamePrefix;

  /**
   * @var string - the base of the route URI to which crud operation strings will be appended.
   */
  public string $routeURIPrefix;

  /**
   * @var int - the number of ancestor resources.
   */
  public int $depth;


  // PERTAINING TO SUB-RESOURCES

  /**
   * @var ResourcePathNodeSchema|null - the node describing the parent resource (if any).
   */
  public ?ResourcePathNodeSchema $parent = null;

  /**
   * @var ResourcePathNodeSchema|null - the node describing the child resource (if any).
   */
  public ?ResourcePathNodeSchema $child = null;

  /**
   * @var string|null - if this is a sub resource, the method on the parent's Model returning
   *   the HasOneOrMany relation.
   */
  public ?string $relationMethodName = null;

  /**
   * @var string|null - if this is a sub resource, the property this resource's model that
   *   references parent model.
   */
  public ?string $parentForeignKeyName = null;

  /**
   * @var string|null - cardinality to the parent; indicates if parent has "one" or "many"
   *   of the sub-resource.
   */
  public ?string $cardinality = null;




  // ---------- CONSTRUCTORS ---------- //

  /**
   * @param string|null $modelClass - Eloquent Model class this resource represents; explicitly passed
   *   for root resources, otherwise derived from other parameter
   * @param ResourcePathNodeSchema|null $parent ,
   * @param Array|null $relationData
   * @throws Exception
   */
  protected function __construct(
    ?string $modelClass,
    ?ResourcePathNodeSchema $parent,
    ?Array $relationData = null
  ) {
    if ($relationData) {

      // Creating a sub-resource
      $this->modelClass = $relationData['qualifiedClassName'];
      $this->parent = $parent;
      $this->parent->child = $this;
      $this->parentForeignKeyName = $relationData['foreignKeyName'];
      $this->relationMethodName = $relationData['methodName'];
      $this->name = strtolower($relationData['methodName']);
      $this->cardinality = $relationData['cardinality'];

    } else {

      // Creating a root-resource
      if (! ($modelClass)) {
        throw new Exception("missing required arguments for root-resource");
      }
      $this->modelClass = $modelClass;
      $this->name = static::deriveNameFromModelClass($modelClass, false);

      // Treat the root resource as a collection.
      $this->cardinality = "many";
    }

    // Common to both root-resource and sub-resource
    $blankInstance = (new $this->modelClass());
    $this->table = $blankInstance->getTable();
    $this->primaryKeyName = $blankInstance->getKeyName();
    $this->uriIdName = static::deriveNameFromModelClass($this->modelClass, false);
    list($this->routeNamePrefix, $this->routeURIPrefix) = $this->generateRouteParameters();
    $this->depth = $parent ? $parent->depth + 1 : 0;
  }

  public static function createRootResourceNode(string $modelClass) {
    return new self($modelClass, null, null);
  }

  /**
   * Deep clones the base path and appends a new head.
   *
   * @param ResourcePathNodeSchema $basePathHead
   * @param array $relationData
   * @throws Exception
   */
  public static function createSubResourceNode(
    ResourcePathNodeSchema $basePathHead,
    Array $relationData
  ): ResourcePathNodeSchema {

    $clonedBasePathHead = clone $basePathHead;
    for ($n = $clonedBasePathHead; $n; $n = $n->parent) {
      if ($n->parent) {
        $n->parent = clone $n->parent;
        $n->parent->child = $n;
      }
    }

    return new self(null, $clonedBasePathHead, $relationData);
  }


  // ---------- METHODS ---------- //

  /**
   * Traverse ancestor nodes to form route name and URI.
   */
  public function generateRouteParameters() {
    $parent = $this->parent;
    $routeName = $this->name;
    $uri = $this->name;
    while ($parent) {
      $routeName = $parent->name . "." . $routeName;

      $uri = $parent->name .
        ($parent->cardinality === "many" ? '/{' . $parent->uriIdName . '}/' : "/") .
        $uri;

      $parent = $parent->parent;
    }
    $apiUriPrefix = config('auto-crud.api-uri-prefix');
    $apiRouteNamePrefix = config('auto-crud.api-route-name-prefix');
    $uri = ($apiUriPrefix ? "$apiUriPrefix/" : "") . $uri;
    $routeName = ($apiRouteNamePrefix ? "$apiRouteNamePrefix." : "") . $routeName;
    return [$routeName, $uri];
  }

  public function instantiateModel($id) {
    return $this->modelClass::find($id);
  }

  /**
   * Given a stack of resource ids [i, j, k, ...], corresponding to
   * a REST API endpoint URI parameters, for example, /users/i/posts/j/comments/k...,
   * verify the relations implied by the path actually exist (eg, there is User i,
   * with Post j, having Comment k).
   *
   * @param array $uriIdStack - stack of ids, with one for this resource schema type
   *   and one for each ancestor node
   *
   * @returns bool - true if the relations expressed by the URI path are valid
   */
  public function verifyLineage(array $uriIdStack) {
    for ($node = $this; $node; $node = $node->parent) {

      // If no further relations to examine, verification succeeded
      if (!$node->parent) {
        return true;
      }

      // If the current child-parent relation is the parent "has one" type,
      // we skip the check, since the child id is not passed
      if ($node->cardinality === "one") {
        $node = $node->parent;
        continue;
      }

      // There is one or more parent-child relationship left to examine, so there
      // should be at least two ids left in the stack (the child, then parent).
      if (count($uriIdStack) < 2) {
        return false;
      }

      // Query DB to confirm the parent-child relationship exists
      $childId = array_pop($uriIdStack);
      $parentId = end($uriIdStack);
      $query =
        "select 1 from {$node->table} ".
        "where {$node->primaryKeyName} = ? ".
        "and {$node->parentForeignKeyName} = ? ".
        "limit 1";
      $result = DB::select($query, [$childId, $parentId]);
      if (count($result) === 0) {
        return false;
      }
    }

    return true;
  }

  public function generateRouteUrl(bool $requiresResourceId) : string {

    $url = $this->routeURIPrefix;

    if ($requiresResourceId && !$this->isHasOneRelation()) {
      return $url . '/{' . $this->uriIdName . '}';
    }

    return $url;
  }

  public function isHasOneRelation(): bool {
    return $this->cardinality === "one";
  }

  /**
   * If foo has a has-one relation bar, bar's id is not passed in the
   * API route URI; instead we have 'foo/{foo}/bar'. This method
   * find's bar id, given there is only one for each foo.
   *
   * @param $uriIdStack - stack of id's passed in via route URI.
   * @return int - id of this resource node instance, given parent.
   */
  public function inferHasOneId($uriIdStack): int {
    $parentId = end($uriIdStack);
    $query =
      "select id from {$this->table} ".
      "where {$this->parentForeignKeyName} = ? ".
      "limit 1";
    $result = DB::select($query, [$parentId]);
    return $result[0]->id;
  }

  public function getRoot() : ResourcePathNodeSchema {
    $node = $this;

    while ($node->parent) {
      $node = $node->parent;
    }

    return $node;
  }

  // ---------- HELPERS ---------- //

  protected static function deriveNameFromModelClass($modelType, $plural = false) {
    $modelName = last(explode('\\', $modelType));
    $name = strtolower($modelName);
    return $plural ? static::pluralize($name) : $name;
  }

  public static function pluralize($singular) {
    $last_letter = strtolower($singular[strlen($singular)-1]);
    switch($last_letter) {
      case 'y':
        return substr($singular,0,-1).'ies';
      case 's':
        return $singular.'es';
      default:
        return $singular.'s';
    }
  }

}