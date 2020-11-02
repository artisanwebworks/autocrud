<?php


namespace ArtisanWebworks\AutoCrud\Test;


use ArtisanWebworks\AutoCrud\GenericAPIController;
use ArtisanWebworks\AutoCrud\Test\Fixtures\User;

class UriIdCastToIntTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(User::class);

//    static::printRoutes();
  }

  /** @test */
  public function user_id_as_string_casted_to_int() {

    $uri = route('api.user.foomodels.create', ['user' => "$this->loggedInUserId"]);
    $args = ['name' => 'new foo'];
    $response = $this->post($uri, $args);
    $response->assertJson($args);
    $response->assertStatus(200 /** OK */);
  }
}