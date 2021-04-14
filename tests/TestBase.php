<?php

namespace ArtisanWebworks\AutoCrud\Test;

use ArtisanWebworks\AutoCrud\AutoCrudServiceProvider;
use ArtisanWebworks\AutoCrud\Test\Fixtures\User;
use Illuminate\Support\Facades\Auth;
use Orchestra\Testbench\TestCase;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;

abstract class TestBase extends TestCase {

  protected $loggedInUserId;

  /**
   * Define environment setup.
   *
   * @param Application $app
   * @return void
   */
  protected function getEnvironmentSetUp($app) {

    // Setup default database to use sqlite :memory:
    $app['config']->set('database.default', 'testbench');
    $app['config']->set(
      'database.connections.testbench',
      [
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'prefix'   => '',
      ]
    );

    $app['config']->set('auto-crud.models-directory', '../../../../../tests/Fixtures');
    $app['config']->set('auto-crud.relations-output', '../../../../tests/output/relations.js');
    $app['config']->set('auto-crud.access-rules', [
      ['user-id-property' => 'id', 'model' => 'ArtisanWebworks\AutoCrud\Test\Fixtures\User'],
      ['user-id-property' => 'user_id']
    ]);
  }

  /**
   * Specify test environment setup before each test.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    $this->loggedInUserId = (static::mockLoggedInUser())->id;
  }

  protected function getPackageProviders($app) {
    return [AutoCrudServiceProvider::class];
  }

  protected static function mockLoggedInUser(): User {
    $loggedInUser = User::create(['username' => 'mr mock']);
    Auth::shouldReceive('id')->andReturn($loggedInUser->id);
    return $loggedInUser;
  }

  protected static function printRoutes() {
    echo "\nROUTES\n";
    $routes = collect(Route::getRoutes())->each(
      function ($route) {
        echo $route->getName() . "  --  " . $route->uri() . "\n";
      }
    );
    echo "\n";
  }

}
