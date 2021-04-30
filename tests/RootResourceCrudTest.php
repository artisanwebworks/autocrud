<?php


namespace ArtisanWebworks\AutoCrud\Test;

// Internal
use ArtisanWebworks\AutoCrud\GenericAPIController;
use ArtisanWebworks\AutoCrud\Test\Fixtures\FooModel;

// Vendor
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class RootResourceCrudTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes for the foomodel resource.
    GenericAPIController::declareRoutes(FooModel::class);

//    static::printRoutes();
  }

  /** @test */
  public function create_root_resource_forbidden() {
    $this->expectException(RouteNotFoundException::class);
    route('api.foomodel.create');
  }

  /** @test */
  public function delete_a_root_resource_forbidden() {
    $this->expectException(RouteNotFoundException::class);
    $uri = route('api.foomodel.delete', ['foomodel' => 1]);
  }

  /** @test */
  public function retrieve_one_resource() {
    $foo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    $uri = route('api.foomodel.retrieve', ['foomodel' => $foo->id]);
    $response = $this->get($uri);
    $response->assertJson(
      [
        'id'      => $foo->id,
        'name'    => 'some foo',
        'user_id' => $this->loggedInUserId
      ]
    );
    $response->assertStatus(200/** OK */);
  }

  /** @test */
  public function retrieve_many_is_denied() {
    // Root level retrieve-all is denied since we don't have a means
    // of associating the call to the logged in user.
    $this->expectException(RouteNotFoundException::class);
    $uri = route('api.foomodel.retrieve-all');
  }

  /** @test */
  public function update_a_foo_resource() {
    $foo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    $uri = route('api.foomodel.update', ['foomodel' => $foo->id]);
    $args = ['name' => 'updated foo'];
    $response = $this->patch($uri, $args);
    $response->assertJson($args);
    $response->assertStatus(200/** OK */);
  }

}