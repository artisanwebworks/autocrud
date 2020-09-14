<?php

namespace ArtisanWebworks\AutoCRUD\Test;

// External
use Orchestra\Testbench\TestCase;
use Illuminate\Contracts\Foundation\Application;


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

}
