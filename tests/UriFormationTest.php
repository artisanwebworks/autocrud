<?php


namespace ArtisanWebworks\AutoCrud\Test;

// Internal
use ArtisanWebworks\AutoCrud\GenericAPIController;
use ArtisanWebworks\AutoCrud\Test\Fixtures\BestFriend;
use ArtisanWebworks\AutoCrud\Test\Fixtures\FooModel;
use ArtisanWebworks\AutoCrud\Test\Fixtures\BarModel;
use Illuminate\Database\Eloquent\Model;

class UriFormationTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(FooModel::class);

//    static::printRoutes();
  }

  /** @test */
  public function has_one_relation_uri_lacks_id() {
    $foo =
      FooModel::create(
        ['name' => 'some foo', 'user_id' => $this->loggedInUserId]
      );
    $foo->bestFriend()->save($bf = new BestFriend(['name' => 'foo-friend']));
    $uri = route("api.foomodel.bestfriend.pets.create", [$foo->id]);
    $this->assertSame("http://localhost/api/foomodel/1000/bestfriend/pets", $uri);
  }
}

