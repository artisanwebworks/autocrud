<?php


namespace ArtisanWebworks\AutoCrud\Test;


use ArtisanWebworks\AutoCrud\GenericAPIController;
use ArtisanWebworks\AutoCrud\Test\Fixtures\FooModel;

class BulkCrudTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(FooModel::class);

//    static::printRoutes();
  }

  /** @test */
  public function bulk_create_root_resource_foo_models() {

    $uri = route('api.foomodels.bulk-create');
    $args = [
      ['name' => 'foo 1', 'user_id' => $this->loggedInUserId],
      ['name' => 'foo 2', 'user_id' => $this->loggedInUserId],
      ['name' => 'foo 3', 'user_id' => $this->loggedInUserId]
    ];

    $response = $this->post($uri, $args);
    $response->assertJson($args);
    $response->assertStatus(200 /** OK */);
  }

  /** @test */
  public function bulk_create_sub_resource_bar_models() {

    $foo = FooModel::create(['name' => 'root foo', 'user_id' => $this->loggedInUserId]);
    $uri = route('api.foomodels.barmodels.bulk-create', ['foomodel' => $foo->id]);
    $args = [
      ['level' => 1], // foo_model fk should be inferred from url
      ['level' => 2],
      ['level' => 3],
    ];
    $response = $this->post($uri, $args);
//    $response->assertJson($args);
    $response->assertStatus(200 /** OK */);
  }

  /** @test */
  public function bulk_create_foo_models_fails_if_any_reject() {

    $uri = route('api.foomodels.bulk-create');
    $args = [
      ['name' => 'foo 1', 'user_id' => $this->loggedInUserId],
      ['name' => 'foo 2', 'user_id' => $this->loggedInUserId],
      ['name' => 'foo 3', 'user_id' => 777]
    ];

    $response = $this->post($uri, $args);
    $response->assertJson(
      ["errors" => ["client doesn't have access to resource"]]
    );

    $response->assertStatus(403 /** FORBIDDEN */);
  }
}