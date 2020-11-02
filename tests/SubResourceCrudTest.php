<?php


namespace ArtisanWebworks\AutoCrud\Test;

// Internal
use ArtisanWebworks\AutoCrud\GenericAPIController;
use ArtisanWebworks\AutoCrud\Test\Fixtures\FooModel;
use ArtisanWebworks\AutoCrud\Test\Fixtures\BarModel;

class SubResourceCrudTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(FooModel::class);

//    static::printRoutes();
  }

  /** @test */
  public function create_bar_as_subresource_of_foo() {
    $foo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    $uri = route("api.foomodel.barmodels.create", [$foo->id]);
    $args = ['level'=> 1];
    $response = $this->post($uri, $args);
    // confirm foreign key to parent resource is inferred from URL parameters
    $response->assertJson(array_merge($args, ['foo_model_id' => $foo->id]));
    $response->assertStatus(200);
  }

  /** @test */
  public function retrieve_bar_as_subresource_of_foo() {
    $foo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    $args = ['level'=> 1];
    $bar = $foo->barModels()->save(BarModel::make($args));
    $uri = route("api.foomodel.barmodels.retrieve", ['foomodel' => $foo->id, 'barmodel' => $bar->id]);
    $response = $this->get($uri);
    $response->assertJson($args);
    $response->assertStatus(200);
  }

  /** @test */
  public function retrieve_all_bars_as_subresources_of_foo() {
    $foo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    $foo->barModels()->save(BarModel::make(['level' => 1]));
    $foo->barModels()->save(BarModel::make(['level' => 2]));
    $uri = route("api.foomodel.barmodels.retrieve-all", ['foomodel' => $foo->id]);
    $response = $this->get($uri);
    $response->assertJson([
      ['level' => 0],
      ['level' => 1],
      ['level' => 2],
    ]);
    $response->assertStatus(200);
  }

  /** @test */
  public function update_bar_as_subresource_of_foo() {
    $foo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    $bar = $foo->barModels()->save(BarModel::make(['level' => 1]));
    $uri = route("api.foomodel.barmodels.update", ['foomodel' => $foo->id, 'barmodel' => $bar->id]);
    $response = $this->patch($uri, ['level' => 2]);
    $response->assertStatus(200);
    $bar->refresh();
    $this->assertEquals(2, $bar->level);
  }

  /** @test */
  public function delete_bar_as_subresource_of_foo() {
    $foo = FooModel::create(['name' => 'some foo', 'user_id' => $this->loggedInUserId]);
    $bar = $foo->barModels()->save(BarModel::make(['level' => 1]));
    $uri = route("api.foomodel.barmodels.delete", ['foomodel' => $foo->id, 'barmodel' => $bar->id]);
    $response = $this->delete($uri);
    $response->assertStatus(204/** NO CONTENT */);
    $this->assertNull(BarModel::find($bar->id));
  }
}