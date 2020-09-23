<?php


namespace ArtisanWebworks\AutoCRUD;


trait AuthorizesCRUDOperations {

  /**
   * Determine if the given user can perform the given CRUD operation on the
   * given resource.
   *
   * Traverse the chain of resource-relations, starting at the target node,
   * and attempt to satisfy an authorization rule specified in the 'access-rules' config.
   *
   * The default rule config is such that a target resource or its parent must be the User
   * resource, with id matching the logged in user, OR, if any resource
   * along the chain has a 'user_id' property, that will be matched against
   * the logged in user to determine access.
   *
   * A special case is the 'retrieve-all' operation, which targets a resource
   * collection. In this case, it must be a parent node which satisfies access,
   * as there is no single target node.
   *
   * NOTE: "retrieve-all" called against a root resource will always deny;
   * we will consider explicitly whitelisting exceptions to this as needed in the future.
   *
   * @param ResourceNodeSchema $node - the target node
   * @param $op - an ArtisanWebworks\AutoCRUD\Operation constant
   * @param $userId - the id we are authorizing against, typically the logged in user
   * @param $resourceIdStack - id's corresponding to the chain of resources, with
   *   the root resource as the first element
   * @return bool: true if the operation is authorized
   */
  protected static function authorized(ResourceNodeSchema $node, $op, $userId, $resourceIdStack) {

    // In the case of retrieve-all & create, the id on the top of the resource
    // stack corresponds to the parent, whereas for the other operations
    // the topmost id is the target resource instance
    $nextNode = $op === Operation::RETRIEVE_ALL || $op === Operation::CREATE ?
      $node->parent : $node;

    while ($resourceIdStack) {
      $id = array_pop($resourceIdStack);
      $instance = $nextNode->instantiateModel($id);
      if (!$instance) {
        return false;
      }
      if (static::userCanAccessInstance($userId, $instance)) {
        return true;
      }
      $nextNode = $nextNode->parent;
    }

    return false;
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
        echo "$rulePrefix property condition resolves rule to " . ($resolution? "accept" : "reject") . "\n";
        return $resolution;
      }

      echo "$rulePrefix rule indeterminate since property not observed on instance\n";
    }

    // No rule can make a determination so reject.
    echo "\tall rules failed to make determination so defaulting to reject";
    return false;
  }
}