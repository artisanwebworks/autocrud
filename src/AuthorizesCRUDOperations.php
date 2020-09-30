<?php


namespace ArtisanWebworks\AutoCRUD;


use Illuminate\Database\Eloquent\Model;

trait AuthorizesCRUDOperations {

  protected static function createOrUpdateIsAuthorized(
    ResourceNodeSchema $node,
    $userId,
    $ancestorIdStack,
    $modelPreview
  ) {

    // First attempt to settle authorization with the preview model
    $outcome = static::attemptSettleAuthorizationWithInstance($userId, $modelPreview);
    if ($outcome !== "indeterminate") {
      return $outcome === "accept";
    }

    // Failing that, attempt to settle authorization using an ancestor resource;
    // called in the context of an update, we expect the updated entity's id
    //  to have already been popped off the stack, leaving only ancestor ids.
    if (!$ancestorIdStack || !$node->parent) {
      return false;
    }
    $nextNode = $node->parent;

    return self::settleAuthorizationWithResourceLineage(
      $ancestorIdStack,
      $nextNode,
      $userId
    );
  }

  protected static function retrieveOneIsAuthorized(
    ResourceNodeSchema $schema,
    $userId,
    $lineageIdStack
  ) {
    return self::settleAuthorizationWithResourceLineage(
      $lineageIdStack,
      $schema,
      $userId
    );
  }

  protected static function retrieveManyIsAuthorized(
    ResourceNodeSchema $schema,
    $userId,
    $ancestorIdStack
  ) {
    if (!($ancestorIdStack && $schema->parent)) {
      // TODO: root level retrieve-all should be whitelisted
      return false;
    }

    // The id at the top of the resource stack corresponds to an instance
    // of the parent resource. For example if the call is to retrieve all
    // users/i/posts/j/comments, we begin authorization by examining post j.
    $nextNode = $schema->parent;

    return self::settleAuthorizationWithResourceLineage(
      $ancestorIdStack,
      $nextNode,
      $userId
    );
  }

  protected static function deleteIsAuthorized(
    ResourceNodeSchema $schema,
    $userId,
    $lineageIdStack
  ) {
    return self::settleAuthorizationWithResourceLineage(
      $lineageIdStack,
      $schema,
      $userId
    );
  }

  /**
   * @param $lineageIdStack
   * @param ResourceNodeSchema|null $nextNode
   * @param $userId
   * @return bool
   */
  protected static function settleAuthorizationWithResourceLineage(
    $lineageIdStack,
    ?ResourceNodeSchema $nextNode,
    $userId
  ): bool {

    while ($lineageIdStack && $nextNode) {

      $id = array_pop($lineageIdStack);
      $instance = $nextNode->instantiateModel($id);
      if (!$instance) {
        return false;
      }

      $outcome = static::attemptSettleAuthorizationWithInstance($userId, $instance);
      if ($outcome !== "indeterminate") {
        return $outcome === "accept";
      }

      $nextNode = $nextNode->parent;
    }

    return false;
  }

  /**
   * Attempt to settle the authorization outcome against an instance in the
   * resource chain, by applying the 'auto-crud.access-rules' config.
   *
   * @param $userId - the logged in user id
   * @param Model $instance - an Eloquent model, or the create parameters for one
   * @return string - one of "accept", "reject", "indeterminate"
   */
  private static function attemptSettleAuthorizationWithInstance($userId, Model $instance) {

    echo "attempting to authorize $userId against instance $instance->id\n";
    $instanceClass = get_class($instance);
    $ruleIndex = 0;
    foreach (config('auto-crud.access-rules') as $rule) {
      $rulePrefix = "\tRULE $ruleIndex: ";
      $ruleIndex++;

      // An access rule can optionally be specific to a given model class.
      if (isset($rule['model']) && $rule['model'] !== $instanceClass) {
        echo "$rulePrefix indeterminate due to class condition mismatch\n";
        continue;
      }

      // The first qualifying property settles the authorization attempt;
      // denying if its value does not match.
      $propertyName = $rule['user-id-property'];
      if (isset($instance[$propertyName])) {
        $resolution = $instance[$propertyName] === $userId ? "accept" : "reject";
        echo "$rulePrefix user-id-property condition resolves rule to $resolution\n";
        return $resolution;
      }

      echo "$rulePrefix rule indeterminate since user-id property not observed on instance\n";
    }

    // No rule can make a determination so reject.
    echo "\tall rules failed to make determination so returning indeterminate";
    return "indeterminate";
  }
}