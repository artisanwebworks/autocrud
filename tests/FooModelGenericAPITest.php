<?php


namespace ArtisanWebworks\AutoCRUD\Test;

// Internal
use ArtisanWebworks\AutoCRUD\GenericAPIController;
use ArtisanWebworks\AutoCRUD\Test\Fixtures\FooModel;

// Vendor
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class FooModelGenericAPITest extends BaseAutoCRUDTest {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(FooModel::class);

//    echo "\nROUTES\n";
//    $routes = collect(Route::getRoutes())->each(function ($route) {
//      echo $route->getName() . "  --  " . $route->uri() . "\n";
//    });
//    echo "\n";

    // Setup base DB state for all tests
    FooModel::create(['name' => 'some foo']); // ID = 1
    FooModel::create(['name' => 'some other foo']); // ID = 2
  }

  /** @test */
  public function retrieve_one_foo_resource() {
    $uri = route('api.foomodel.retrieve', ['id' => 1]);
    $response = $this->get($uri);
    $response->assertJson(['data' => ['name' => 'some foo']]);
    $response->assertStatus(200 /** OK */);
  }

  /** @test */
  public function retrieve_all_foo_resources() {
    $uri = route('api.foomodel.retrieve-all');
    $response = $this->get($uri);
    $response->assertJson([
      'data' => [
        ['name' => 'some foo'],
        ['name' => 'some other foo']
      ]
    ]);
    $response->assertStatus(200 /** OK */);
  }

  /** @test */
  public function create_a_new_foo_resource() {
    $uri = route('api.foomodel.create');
    $response = $this->post($uri, ['name' => 'new foo']);
    $response->assertJson(['data' => ['name' => 'new foo']]);
    $response->assertStatus(201 /** CREATED */);
  }

  /** @test */
  public function update_a_foo_resource() {
    $uri = route('api.foomodel.update', ['id' => 1]);
    $response = $this->patch($uri, ['name' => 'updated foo']);
    $response->assertJson(['data' => ['name' => 'updated foo']]);
    $response->assertStatus(200 /** OK */);
  }

  /** @test */
  public function update_with_validation_rule_violation_returns_error() {
    $uri = route('api.foomodel.update', ['id' => 1]);
    $response = $this->patch($uri, ['name' => 'fu']);
    $response->assertJson(['errors' => ['name' => 'name must be at least 3 characters']]);
    $response->assertStatus(422 /** UNPROCESSABLE ENTITY */);
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
    $response->assertStatus(204 /** NO CONTENT */);

    // Try to retrieve to confirm actually deleted
    $uri = route('api.foomodel.retrieve', ['id' => 1]);
    $response = $this->get($uri);
    $response->assertStatus(404);
  }

}