<?php


namespace ArtisanWebworks\AutoCRUD\Test;

// Internal
use ArtisanWebworks\AutoCRUD\GenericAPIController;
use ArtisanWebworks\AutoCRUD\Test\Fixtures\FooModel;
use ArtisanWebworks\AutoCRUD\Test\Fixtures\BarModel;

class SubResourceCRUDTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(FooModel::class);

    static::printRoutes();
  }

  /** @test */
  public function create_bar_as_subresource_of_foo() {
    $foo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    $uri = route("api.foomodels.barmodels.create", [$foo->id]);
    $args = ['level'=> 1];
    $response = $this->post($uri, $args);
    // confirm foreign key to parent resource is inferred from URL parameters
    $response->assertJson(['data' => array_merge($args, ['foo_model_id' => $foo->id])]);
    $response->assertStatus(200);
  }

  /** @test */
  public function retrieve_bar_as_subresource_of_foo() {
    $foo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    $args = ['level'=> 1];
    $bar = $foo->barModels()->save(BarModel::make($args));
    $uri = route("api.foomodels.barmodels.retrieve", ['foomodel' => $foo->id, 'barmodel' => $bar->id]);
    $response = $this->get($uri);
    $response->assertJson(['data' => $args]);
    $response->assertStatus(200);
  }

  /** @test */
  public function retrieve_all_bars_as_subresources_of_foo() {
    $foo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    $foo->barModels()->save(BarModel::make(['level' => 1]));
    $foo->barModels()->save(BarModel::make(['level' => 2]));
    $uri = route("api.foomodels.barmodels.retrieve-all", ['foomodel' => $foo->id]);
    $response = $this->get($uri);
    $response->assertJson(['data' => [
      ['level' => 1],
      ['level' => 2],
    ]]);
    $response->assertStatus(200);
  }

  /** @test */
  public function update_bar_as_subresource_of_foo() {
    $foo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    $bar = $foo->barModels()->save(BarModel::make(['level' => 1]));
    $uri = route("api.foomodels.barmodels.update", ['foomodel' => $foo->id, 'barmodel' => $bar->id]);
    $response = $this->patch($uri, ['level' => 2]);
    $response->assertStatus(200);
    $bar->refresh();
    $this->assertEquals(2, $bar->level);
  }

  /** @test */
  public function delete_bar_as_subresource_of_foo() {
    $foo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    $bar = $foo->barModels()->save(BarModel::make(['level' => 1]));
    $uri = route("api.foomodels.barmodels.delete", ['foomodel' => $foo->id, 'barmodel' => $bar->id]);
    $response = $this->delete($uri);
    $response->assertStatus(204/** NO CONTENT */);
    $this->assertNull(BarModel::find($bar->id));
  }
}