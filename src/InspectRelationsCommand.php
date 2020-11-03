<?php

namespace ArtisanWebworks\AutoCrud;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

include_once "relation-reflection.php";

class InspectRelationsCommand extends Command {

  protected $signature = 'autocrud:inspect-relations';

  public function handle() {

    $modelsPath = app_path(config('auto-crud.models-directory'));
    $resourceRelations = static::reflectRelationsFromModels($modelsPath);
    $jsOutput = 'export default ' . json_encode($resourceRelations, JSON_PRETTY_PRINT);
    $outputPath = base_path(config('auto-crud.relations-output'));
    file_put_contents($outputPath, $jsOutput);
    echo $outputPath . "\n";
  }

  protected static function reflectRelationsFromModels($path) {

    // Defines the set of immediate relations belonging to each model;
    // a mapping of "resource type" (front-end term for a model, which is
    // the Model subclass name, in lower case), to the set of immediate
    // relations.
    $resourceRelationsMap = [];

    foreach (File::allFiles($path) as $file) {
      $contents = $file->getContents();

      // Parse out the namespace
      preg_match('/.*namespace\s*(.*);/', $contents, $matches);
      $namespace = (count($matches) === 2) ? $matches[1] : false;
      if (!$namespace) {
        continue;
      }

      // Parse out classname
      preg_match('/^\s*class\s*(\S*)/m', $contents, $matches);
      $shortClassName = (count($matches) === 2) ? $matches[1] : false;
      if (!$shortClassName) {
        continue;
      }
      $qualifiedClass = "\\$namespace\\$shortClassName";

      // Filter out non-Model classes
      $reflection = new \ReflectionClass($qualifiedClass);
      $isModel = $reflection->isSubclassOf(Model::class) && !$reflection->isAbstract();
      if (!$isModel) {
        continue;
      }

      $resourceType = strtolower($shortClassName);
      foreach (inspectRelations($qualifiedClass, $reflection) as $relation) {
        $resourceRelationName = $relation['methodName'];
        $resourceRelationsMap[$resourceType][$resourceRelationName] = [
          'type' => strtolower($relation['shortClassName']),
          'cardinality' => $relation['cardinality']
        ];
      }
    }

    return $resourceRelationsMap;
  }

}