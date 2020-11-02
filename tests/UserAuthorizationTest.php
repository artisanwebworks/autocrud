<?php


namespace ArtisanWebworks\AutoCrud\Test;

// Internal
use ArtisanWebworks\AutoCrud\GenericAPIController;
use ArtisanWebworks\AutoCrud\ResourceNodeSchema;
use ArtisanWebworks\AutoCrud\Test\Fixtures\BazModel;
use ArtisanWebworks\AutoCrud\Test\Fixtures\FooModel;
use ArtisanWebworks\AutoCrud\Test\Fixtures\BarModel;
use ArtisanWebworks\AutoCrud\Test\Fixtures\User;

// Vendor
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Class UserAuthorizationTest
 *
 * Test package's authorization mechanism whereby the target resource of a CRUD operation
 * must be linked, directly or indirectly, to the Laravel Auth user. See 'auto-crud.php'
 * and ResourceNode::authorize().
 *
 * @package ArtisanWebworks\AutoCrud\Test
 */
class UserAuthorizationTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(User::class);

//    static::printRoutes();
  }
  
  /** @test */
  public function retrieve_all_foo_denied_because_ancestor_is_other_user() {
    $foo = FooModel::create(['name' => 'related foo', 'user_id' => 777]);
    $bar = $foo->barModels()->save(BarModel::make(['level' => 1]));
    $baz = $bar->bazModels()->save(BazModel::make(['can-recognize' => false]));

    $uri = route("api.user.foomodels.barmodels.bazmodels.retrieve", [
      'user' => $this->loggedInUserId,
      'foomodel' => $foo->id, // belongs to different user!
      'barmodel' => $bar->id,
      'bazmodel' => $baz->id
    ]);

    $response = $this->get($uri);
    $response->assertStatus(403);
  }

}