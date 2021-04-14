<?php

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;


/**
 * @param $class
 * @param null $reflection - optionally provided if available at callsite, otherwise we will
 *   build it ourselves
 * @return array
 * @throws ReflectionException
 */
function enumerateRelations($class, $reflection = null) {

  if (!$reflection) {
    $reflection = new \ReflectionClass($class);
  }

  $relations = [];

  foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {

    // Eloquent Model relations are methods returning a Relation subclass
    $returnType = $method->getReturnType();
    if (!$returnType) {
      continue;
    }

    // Right now we are only concerned with HasOne and HasMany relations
    $isHasMany = strcmp($returnType, HasMany::class) === 0;
    $isHasOne = strcmp($returnType, HasOne::class) === 0;
    if ($isHasOne || $isHasMany) {

      $blankEntity = new $class();
      $relationMethodName = $method->getName();
      $relationInstance = $blankEntity->$relationMethodName();

      $qualifiedClassName = get_class($relationInstance->getRelated());
      $relations[] = [
        'methodName' => $relationMethodName,
        'qualifiedClassName' => $qualifiedClassName,
        'shortClassName' => last(explode("\\", $qualifiedClassName)),
        'foreignKeyName' => $relationInstance->getForeignKeyName(),
        'cardinality' => $isHasMany ? "many" : "one"
      ];
    }
  }

  return $relations;
}
