<?php


namespace ArtisanWebworks\AutoCRUD\Test;

// Internal
use ArtisanWebworks\AutoCRUD\GenericAPIController;
use ArtisanWebworks\AutoCRUD\Test\Fixtures\FooModel;

// Vendor
use Illuminate\Support\Facades\Auth;

class RootEndpointCRUDTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes for the foomodel resource.
    GenericAPIController::declareRoutes(FooModel::class);

    static::printRoutes();
  }

  /** @test */
  public function create_a_new_foo_resource() {
    $uri = route('api.foomodels.create');
    $args = ['name' => 'new foo', 'user_id' => $this->loggedInUserId];
    $response = $this->post($uri, $args);
    $response->assertJson(['data' => $args]);
    $response->assertStatus(200 /** OK */);

    // Confirm new resource can be fetched
    $fooId = $response->json(['data'])['id'];
    $uri = route('api.foomodels.retrieve', ['foomodel' => $fooId]);
    $response = $this->get($uri);
    $response->assertJson(['data' => $args]);
    $response->assertStatus(200 /** OK */);
  }

  /** @test */
  public function retrieve_one_resource() {
    $foo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    $uri = route('api.foomodels.retrieve', ['foomodel' => $foo->id]);
    $response = $this->get($uri);
    $response->assertJson(
      [
        'data' => [
          'id'      => $foo->id,
          'name'    => 'some foo',
          'user_id' => $this->loggedInUserId
        ]
      ]
    );
    $response->assertStatus(200/** OK */);
  }

  /** @test */
  public function retrieve_many_is_denied() {
    // Root level retrieve-all is denied since we don't have a means
    // of associating the call to the logged in user.
    // TODO: consider authorization bypass whitelist for endpoints returning
    // public data
    $uri = route('api.foomodels.retrieve-all');
    $response = $this->get($uri);
    $response->assertJson(
      [
        "errors" => ["client doesn't have access to resource"]
      ]
    );
    $response->assertStatus(403/** OK */);
  }

  /** @test */
  public function update_a_foo_resource() {
    $foo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    $uri = route('api.foomodels.update', ['foomodel' => $foo->id]);
    $args = ['name' => 'updated foo'];
    $response = $this->patch($uri, $args);
    $response->assertJson(['data' => $args]);
    $response->assertStatus(200/** OK */);
  }

  /** @test */
  public function delete_a_foo_resource() {
    $foo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    $uri = route('api.foomodels.delete', ['foomodel' => $foo->id]);
    $response = $this->delete($uri);
    $response->assertStatus(204/** NO CONTENT */);

    // Try to retrieve to confirm actually deleted
    $uri = route('api.foomodels.retrieve', ['foomodel' => $foo->id]);
    $response = $this->get($uri);
    $response->assertStatus(403/** FORBIDDEN (we don't communicate existence)*/);
  }
}