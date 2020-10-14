<?php


namespace ArtisanWebworks\AutoCrud\Test;


use ArtisanWebworks\AutoCrud\GenericAPIController;
use ArtisanWebworks\AutoCrud\Test\Fixtures\User;
use ArtisanWebworks\AutoCrud\Test\Fixtures\FooModel;

class ExpandedJsonResponseTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(User::class);

//    static::printRoutes();
  }

  /** @test */
  public function user_foomodels_relation_expanded_in_json_response() {

    User::find($this->loggedInUserId)->fooModels()->save(
      FooModel::make( $fooArgs =
        [
          'name' => 'related foo'
        ]
      )
    );

    $uri = route(
      "api.users.retrieve",
      [
        'user'     => $this->loggedInUserId,
      ]
    );

    $response = $this->get($uri);
    $response->assertJson(['foo_models' => [$fooArgs]]);
  }
}