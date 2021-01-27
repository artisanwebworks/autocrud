<?php


namespace ArtisanWebworks\AutoCrud\Test;


use ArtisanWebworks\AutoCrud\GenericAPIController;
use ArtisanWebworks\AutoCrud\Test\Fixtures\FooModel;

class ValidationTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(FooModel::class);

//    static::printRoutes();
  }

  /** @test */
  public function update_with_validation_rule_violation_returns_error() {
    $foo = FooModel::create(['name' => 'related foo', 'user_id' => $this->loggedInUserId]);
    $uri = route('api.foomodel.update', ['foomodel' => $foo->id]);
    $response = $this->patch($uri, ['name' => 'fu']);
    $response->assertJson(
      ['errors' => ['name' => 'name must be at least 3 characters']]
    );
    $response->assertStatus(422 /** UNPROCESSABLE ENTITY */);
  }

  /** @test */
  public function update_with_undefined_id_returns_error() {
    $uri = route('api.foomodel.update', ['foomodel' => 777]);
    $response = $this->patch($uri, ['name' => 'updated foo']);
    $response->assertStatus(403 /** FORBIDDEN */);
  }

  /** @test */
  public function retrieve_invalid_id_returns_error() {
    $uri = route('api.foomodel.retrieve', ['foomodel' => 777]);
    $response = $this->get($uri);
    $response->assertStatus(403 /** FORBIDDEN */);
  }

  /** @test */
  public function update_with_custom_rule_violation_returns_error() {
    $foo = FooModel::create(['name' => 'related foo', 'user_id' => $this->loggedInUserId]);
    $uri = route('api.foomodel.update', ['foomodel' => $foo->id]);
    $response = $this->patch($uri, ['name' => 'fooo']);
    $response->assertJson(
      ['errors' => ['name' => 'no']]
    );
    $response->assertStatus(422 /** UNPROCESSABLE ENTITY */);
  }

}