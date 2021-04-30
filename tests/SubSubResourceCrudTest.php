<?php


namespace ArtisanWebworks\AutoCrud\Test;

// Internal
use ArtisanWebworks\AutoCrud\GenericAPIController;
use ArtisanWebworks\AutoCrud\Test\Fixtures\BestFriend;
use ArtisanWebworks\AutoCrud\Test\Fixtures\FooModel;
use ArtisanWebworks\AutoCrud\Test\Fixtures\BarModel;
use ArtisanWebworks\AutoCrud\Test\Fixtures\User;
use Illuminate\Database\Eloquent\Model;

class SubSubResourceCrudTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(User::class);

    //static::printRoutes();
  }

  /** @test */
  public function delete_bar_as_subresource_of_foo() {

    $foo =
      FooModel::create(
        ['name' => 'some foo', 'user_id' => $this->loggedInUserId]
      );

    $bar = $foo->barModels()->save(BarModel::make(['level' => 1]));

    $uri =
      route(
        "api.user.foomodels.barmodels.delete",
        [
          'user' => $this->loggedInUserId,
          'foomodel' => $foo->id,
          'barmodel' => $bar->id
        ]
      );

    $response = $this->delete($uri);
    $response->assertStatus(204/** NO CONTENT */);
    $this->assertNull(BarModel::find($bar->id));
  }

}

