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

class RootResourceTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes for the foomodel resource.
    GenericAPIController::declareRoutes(FooModel::class);

    static::printRoutes();
  }

  /** @test */
  public function retrieve_one_resource() {
    static::printRoutes();
    $foo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    $uri = route('api.foomodels.retrieve', ['id' => $foo->id]);
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
  public function retrieve_many_resources() {
    Model::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    Model::create(['name' => 'some other foo', 'user_id' => $this->loggedInUserId]);
    $uri = route('api.foomodel.retrieve-all');
    $response = $this->get($uri);
    $response->assertJson(
      [
        'data' => [
          ['name' => 'some foo'],
          ['name' => 'some other foo']
        ]
      ]
    );
    $response->assertStatus(200/** OK */);
  }

  /** @test */
  public function create_a_new_foo_resource() {
    $uri = route('api.foomodels.create');
    $response = $this->post($uri, ['name' => 'new foo']);
    $response->assertJson(['data' => ['name' => 'new foo']]);
    $response->assertStatus(201/** CREATED */);

    // Fetch the newly created resource in a separate call
    $fooId = $response->json(['data'])['id'];
    $uri = route('api.foomodels.retrieve', ['id' => $fooId]);
    $response = $this->get($uri);
    $response->assertJson(
      [
        'data' => [
          'id'      => $fooId,
          'name'    => 'some foo',
          'user_id' => $this->loggedInUserId
        ]
      ]
    );
    $response->assertStatus(200/** OK */);
  }

  /** @test */
  public function update_a_foo_resource() {
    $uri = route('api.foomodel.update', ['id' => 1]);
    $response = $this->patch($uri, ['name' => 'updated foo']);
    $response->assertJson(['data' => ['name' => 'updated foo']]);
    $response->assertStatus(200/** OK */);
  }

  /** @test */
  public function update_with_validation_rule_violation_returns_error() {
    $uri = route('api.foomodel.update', ['id' => 1]);
    $response = $this->patch($uri, ['name' => 'fu']);
    $response->assertJson(
      ['errors' => ['name' => 'name must be at least 3 characters']]
    );
    $response->assertStatus(422/** UNPROCESSABLE ENTITY */);
  }

  /** @test */
  public function update_with_extraneous_argument_returns_error() {
    $uri = route('api.foomodel.update', ['id' => 1]);
    $response = $this->patch($uri, ['name' => 'updated foo', 'bar' => 'baz']);
    $response->assertJson(['errors' => ['bar is an unrecognized field']]);
  }

  /** @test */
  public function update_with_undefined_id_returns_error() {
    $uri = route('api.foomodel.update', ['id' => 777]);
    $response = $this->patch($uri, ['name' => 'updated foo']);
    $response->assertJson(['errors' => ['777 is not a valid foomodel id']]);
  }

  /** @test */
  public function retrieve_invalid_id_returns_error() {
    $uri = route('api.foomodel.retrieve', ['id' => 777]);
    $response = $this->get($uri);
    $response->assertJson(['errors' => ['777 is not a valid foomodel id']]);
    $response->assertStatus(404);
  }

  /** @test */
  public function delete_a_foo_resource() {
    $uri = route('api.foomodel.delete', ['id' => 1]);
    $response = $this->delete($uri);
    $response->assertStatus(204/** NO CONTENT */);

    // Try to retrieve to confirm actually deleted
    $uri = route('api.foomodel.retrieve', ['id' => 1]);
    $response = $this->get($uri);
    $response->assertStatus(404);
  }

  /** @test */
  public function recursive_routes_defined_based_on_model_relations() {
    $mockedAuthUserId = 777;

    var_dump(Auth::id());

    echo "Retrieving via API";
    $response = $this->get('api\\foomodel\\1\\barmodels');
  }

  /** @test */
  public function retrieve_bars_via_foo_parent() {
    $uri = route('api.foomodel.barmodels.retrieve-all');
    $response = $this->get($uri);
    $response->assertStatus(200);
  }

}