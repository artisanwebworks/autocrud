<?php


namespace ArtisanWebworks\AutoCRUD\Test;

// Internal
use ArtisanWebworks\AutoCRUD\GenericAPIController;
use ArtisanWebworks\AutoCRUD\ResourceNodeSchema;
use ArtisanWebworks\AutoCRUD\Test\Fixtures\FooModel;
use ArtisanWebworks\AutoCRUD\Test\Fixtures\BarModel;
use ArtisanWebworks\AutoCRUD\Test\Fixtures\User;

// Vendor
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Class AuthorizationTest
 *
 * Test package's authorization mechanism whereby the target resource of a CRUD operation
 * must be linked, directly or indirectly, to the Laravel Auth user. See 'auto-crud.php'
 * and ResourceNode::authorize().
 *
 * @package ArtisanWebworks\AutoCRUD\Test
 */
class EndpointAuthorizationTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(User::class);

    static::printRoutes();
  }
  
  /** @test */
  public function retrieve_all_foo_accepted_because_parent_is_user_resource() {
    $firstFoo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUser->id]);
    $bar1 = BarModel::create(['level' => 1, 'foo_model_id' => $firstFoo->id]);
    $bar2 = BarModel::create(['level' => 2, 'foo_model_id' => $firstFoo->id]);
    FooModel::create(['name' => 'some other foo', 'user_id' => 777]);
    $response = $this->get(route("api.users.foomodels.retrieve-all", [1]));
    $response->assertStatus(200);
  }

  /** @test */
  public function retrieve_single_foo_accepted_because_parent_is_user_resource() {
    $response = $this->get(route("api.users.foomodels.retrieve", [1, 1]));
    $response->assertStatus(200);
  }
}