<?php

namespace ArtisanWebworks\AutoCRUD\Test;

use ArtisanWebworks\AutoCRUD\AutoCRUDServiceProvider;
use Orchestra\Testbench\TestCase;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;

abstract class BaseAutoCRUDTest extends TestCase {

  /**
   * Define environment setup.
   *
   * @param Application $app
   * @return void
   */
  protected function getEnvironmentSetUp($app) {

    // Setup default database to use sqlite :memory:
    $app['config']->set('database.default', 'testbench');
    $app['config']->set('database.connections.testbench', [
      'driver'   => 'sqlite',
      'database' => ':memory:',
      'prefix'   => '',
    ]);
  }

  /**
   * Specify test environment setup before each test.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
  }

  protected function getPackageProviders($app) {
    return [AutoCRUDServiceProvider::class];
  }


  // ---------- HELPERS ---------- //

  public static function printRoutes() {
    echo "\nROUTES\n";
    $routes = collect(Route::getRoutes())->each(function ($route) {
      echo $route->getName() . "  --  " . $route->uri() . "\n";
    });
    echo "\n";
  }

}
