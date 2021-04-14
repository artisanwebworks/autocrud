<?php


namespace ArtisanWebworks\AutoCrud;


use Illuminate\Database\Eloquent\Model;

trait AuthorizesCrudOperations {

  /**
   * @param ResourcePathNodeSchema $node
   * @param $userId
   * @param $ancestorIdStack
   * @param $preview -- a single model or an array of models (for bulk operations)
   * @return bool
   */
  protected static function createOrUpdateIsAuthorized(
    ResourcePathNodeSchema $node,
    $userId,
    $ancestorIdStack,
    $preview
  ) {

    // First attempt to settle authorization with the preview model
    $outcome = static::attemptSettleAuthorizationWithInstance($userId, $preview);
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
    ResourcePathNodeSchema $schema,
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
    ResourcePathNodeSchema $schema,
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
    ResourcePathNodeSchema $schema,
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
   * @param ResourcePathNodeSchema|null $nextNode
   * @param $userId
   * @return bool
   */
  protected static function settleAuthorizationWithResourceLineage(
    $lineageIdStack,
    ?ResourcePathNodeSchema $nextNode,
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
   * @param Model|array $preview - an Eloquent model, or array of models
   * @return string - one of "accept", "reject", "indeterminate"
   */
  private static function attemptSettleAuthorizationWithInstance($userId, $preview) {

    // Normalize as array
    $preview = is_array($preview) ? $preview : [$preview];

    // We assume preview instances are all of the same class
    $instanceShortClassName = last(explode('\\', get_class(head($preview))));

    // Iterate through each access rule specified in the config
    $ruleIndex = 0;
    foreach (config('auto-crud.access-rules') as $rule) {
      $ruleIndex++;

      // An access rule can optionally be specific to a given model class
      if (isset($rule['model']) && $rule['model'] !== $instanceShortClassName) {
        continue;
      }

      // The first qualifying property settles the authorization attempt;
      // denying if its value does not match.
      $propertyName = $rule['user-id-property'];

      // Attempt to settle authorization by looking for $propertyName on
      // all the preview instances.
      $allAccept = true;
      foreach ($preview as $instance) {

        if (isset($instance[$propertyName])) {
          if($instance[$propertyName] != $userId) {

            // A property mismatch on any one instance, rejects for all
            return "reject";
          }
        } else {

          // All entities in the preview set must establish acceptance, or none can.
          // We continue iterating since, there is still the possibility of rejection.
          $allAccept = false;
        }
      }

      if ($allAccept) {
        return "accept";
      }

      // try the next rule...
    }

    // No rule can make a determination so reject.
    return "indeterminate";
  }
}