<?php


namespace ArtisanWebworks\AutoCRUD\Test;

// Internal
use ArtisanWebworks\AutoCRUD\GenericAPIController;
use ArtisanWebworks\AutoCRUD\ResourceNode;
use ArtisanWebworks\AutoCRUD\Test\Fixtures\FooModel;
use ArtisanWebworks\AutoCRUD\Test\Fixtures\BarModel;
use ArtisanWebworks\AutoCRUD\Test\Fixtures\User;

// Vendor
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Class AuthorizationTest
 *
 * Test package's authorization mechanism where by the target resource of a CRUD operation
 * must be linked, directly or indirectly, to the Laravel Auth user. See 'auto-crud.php'
 * and ResourceNode::authorize().
 *
 * @package ArtisanWebworks\AutoCRUD\Test
 */
class AuthorizationTest extends BaseAutoCRUDTest {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(User::class);

    // Mock the the logged in user id return from Auth Facade
    $loggedInUser = User::create(['username' => 'mr mock']);
    Auth::shouldReceive('id')->andReturn($loggedInUser->id);

    // FooModel of ID:1
    // (related to Auth User)
    $firstFoo = FooModel::create(['name' => 'some foo', 'user_id' => $loggedInUser->id]);
    $bar1 = BarModel::create(['level' => 1, 'foo_model_id' => $firstFoo->id]);
    $bar2 = BarModel::create(['level' => 2, 'foo_model_id' => $firstFoo->id]);
    
    // FooModel of ID:2
    // (not related to Auth User)
    FooModel::create(['name' => 'some other foo', 'user_id' => 777]);

    static::printRoutes();
  }
  
  /** @test */
  public function retrieve_all_foo_accepted_because_parent_is_user_resource() {
    $this->get(route("api.user.foomodels.retrieve-all", ['id' => 1]));
  }

}