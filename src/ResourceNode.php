<?php


namespace ArtisanWebworks\AutoCRUD;


use Illuminate\Auth\Access\AuthorizationException;
use function PHPUnit\Framework\isFalse;

/**
 * Class ResourceNode
 *
 * Represents an API resource and corresponding Eloquent model, optionally
 * as a sub-resource belonging to a chain of 1 or more parent resources.
 *
 * @package ArtisanWebworks\AutoCRUD
 */
class ResourceNode {

  /**
   * @var string: Each node corresponds to an Eloquent Model (or subclass) which is to
   *   be exposed as an API resource
   */
  public string $modelType;

  public ?ResourceNode $parent;

  /**
   * @var bool: Determines if the URI and route names are expressed as singular or plural.
   */
  public bool $hasSiblings;

  /**
   * @var string The resource's name as expressed in URI and route name.
   */
  public string $shortName;


  public string $routeName;
  public string $routeURI;

  /**
   * @var int Count of nodes between this resource and the root.
   *
   * For example, if the current node represents Widget, exposed as
   * members of the User resource (accessed at a URI like user/1/widgets),
   * then the depth is 1.
   */
  public int $depth;

  /**
   * RelationLineageNode constructor.
   * @param string $modelType
   * @param ResourceNode|null $parent ,
   * @param bool $hasSiblings
   * @param string|null $shortName : overrides the default name derived from model type.
   */
  public function __construct(
    string $modelType,
    ?ResourceNode $parent,
    bool $hasSiblings,
    string $shortName = null
  ) {
    $this->modelType = $modelType;
    $this->parent = $parent;
    $this->hasSiblings = $hasSiblings;
    $this->shortName = $shortName ?? static::deriveShortName($modelType);
    list($this->routeName, $this->routeURI) = $this->generateRouteParameters();
    $this->depth = $parent ? $parent->depth + 1 : 0;
  }

  /**
   * Traverse ancestor nodes to form route name and URI.
   */
  public function generateRouteParameters() {
    $parent = $this->parent;
    $routeName = $this->shortName . ($this->hasSiblings ? 's' : '');
    $uri = $routeName;
    while ($parent) {
      $routeName = $parent->shortName . "." . $routeName;
      $uri = $parent->shortName . '\\{id}\\' . $uri;
      $parent = $parent->parent;
    }
    $routeName = "api." . $routeName;
    $uri = "api\\" . $uri;
    return [$routeName, $uri];
  }

  private static function deriveShortName($modelType) {
    $modelName = last(explode('\\', $modelType));
    return strtolower($modelName);
  }

  /**
   * A property on the resource, or one of it's parent resources, must satisfy
   * an authorization rule specified in the 'access-rules' config.
   *
   * The default rule is the target resource or its parent must be the User
   * resource, with id matching the logged in user, OR, if any resource
   * along the chain has a 'user_id' property, that will be matched against
   * the logged in user to determine access.
   *
   * A special case is the 'retrieve-all' operation, which targets a resource
   * collection. In this case, it must be a parent node which satisfies access.
   *
   * NOTE: "retrieve-all" called against a root resource will always deny;
   * we can consider explicitly whitelisting exceptions to this as needed in the future.
   *
   * @param $op - an ArtisanWebworks\AutoCRUD\Operation constant
   * @param $userId - the id we are authorizing against, typically the logged in user
   * @param $resourceIdStack - id's corresponding to the chain of resources, with
   *   the root resource as the first element
   * @return bool: true if the operation is authorized
   */
  public function authorize($op, $userId, $resourceIdStack) {

    // In the case of retrieve-all, the id on the top of the resource
    // stack corresponds to the parent
    $nextNode = $op === Operation::RETRIEVE_ALL ? $this->parent : $this;

    while ($resourceIdStack) {
      $id = array_pop($resourceIdStack);
      $instance = $this->instantiateModel($id);
      if (!$instance || static::userCanAccessInstance($userId, $instance)) {
        throw new ResourceAccessDeniedException();
      }

    }

    return false;
  }

  private function instantiateModel($id) {
    return $this->modelType::find($id);
  }

  /**
   * Attempt to settle the authorization outcome against an instance in the
   * resource chain, based on the 'access-rules' specified in config.
   *
   * @param $userId
   * @param $instance
   * @return bool: true if authorized
   */
  private static function userCanAccessInstance($userId, $instance) {

    echo "attempting to authorize $userId against instance $instance->id\n";
    $instanceClass = get_class($instance);
    $ruleIndex = 0;
    foreach (config('auto-crud.access-rules') as $rule) {
      $rulePrefix = "\tRULE $ruleIndex: ";
      $ruleIndex++;

      // An access rule optionally specific to a model type
      if (isset($rule['model']) && $rule['model'] !== $instanceClass) {
        echo "$rulePrefix indeterminate due to class condition mismatch\n";
        continue;
      }

      // The first qualifying property settles the authorization attempt;
      // denying if its value does not match.
      $propertyName = $rule['property'];
      if (isset($instance[$propertyName])) {
        $resolution = $instance[$propertyName] === $userId;
        echo "$rulePrefix property condition resolved to " . ($resolution? "accept" : "reject") . "\n";
        return $resolution;
      }

      echo "$rulePrefix rule indeterminate since property not observed on instance\n";
    };

    // No rule can make a determination so reject.
    echo "\tall rules failed to make determination so defaulting to reject";
    return false;
  }
}