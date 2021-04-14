<?php


namespace ArtisanWebworks\AutoCrud\Test;

// Internal
use ArtisanWebworks\AutoCrud\GenericAPIController;
use ArtisanWebworks\AutoCrud\Test\Fixtures\BestFriend;
use ArtisanWebworks\AutoCrud\Test\Fixtures\FooModel;
use ArtisanWebworks\AutoCrud\Test\Fixtures\BarModel;
use Illuminate\Database\Eloquent\Model;

class SubResourceCrudTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(FooModel::class);

    static::printRoutes();
  }

  /** @test */
  public function create_bar_as_subresource_of_foo() {
    $foo =
      FooModel::create(
        ['name' => 'some foo', 'user_id' => $this->loggedInUserId]
      );
    $uri = route("api.foomodel.barmodels.create", [$foo->id]);
    $args = ['level' => 1];
    $response = $this->post($uri, $args);
    // confirm foreign key to parent resource is inferred from URL parameters
    $response->assertJson(array_merge($args, ['foo_model_id' => $foo->id]));
    $response->assertStatus(200);
  }

  /** @test */
  public function retrieve_bar_as_subresource_of_foo() {
    $foo =
      FooModel::create(
        ['name' => 'some foo', 'user_id' => $this->loggedInUserId]
      );
    $args = ['level' => 1];
    $bar = $foo->barModels()->save(BarModel::make($args));
    $uri =
      route(
        "api.foomodel.barmodels.retrieve",
        ['foomodel' => $foo->id, 'barmodel' => $bar->id]
      );
    $response = $this->get($uri);
    $response->assertJson($args);
    $response->assertStatus(200);
  }

  /** @test */
  public function retrieve_all_bars_as_subresources_of_foo() {
    $foo =
      FooModel::create(
        ['name' => 'some foo', 'user_id' => $this->loggedInUserId]
      );
    $foo->barModels()->save(BarModel::make(['level' => 1]));
    $foo->barModels()->save(BarModel::make(['level' => 2]));
    $uri =
      route("api.foomodel.barmodels.retrieve-all", ['foomodel' => $foo->id]);
    $response = $this->get($uri);
    $response->assertJson(
      [
        ['level' => 0],
        ['level' => 1],
        ['level' => 2],
      ]
    );
    $response->assertStatus(200);
  }

  /** @test */
  public function update_bar_as_subresource_of_foo() {
    $foo =
      FooModel::create(
        ['name' => 'some foo', 'user_id' => $this->loggedInUserId]
      );
    $bar = $foo->barModels()->save(BarModel::make(['level' => 1]));
    $uri =
      route(
        "api.foomodel.barmodels.update",
        ['foomodel' => $foo->id, 'barmodel' => $bar->id]
      );
    $response = $this->patch($uri, ['level' => 2]);
    $response->assertStatus(200);
    $bar->refresh();
    $this->assertEquals(2, $bar->level);
  }

  /** @test */
  public function delete_bar_as_subresource_of_foo() {
    $foo =
      FooModel::create(
        ['name' => 'some foo', 'user_id' => $this->loggedInUserId]
      );
    $bar = $foo->barModels()->save(BarModel::make(['level' => 1]));
    $uri =
      route(
        "api.foomodel.barmodels.delete",
        ['foomodel' => $foo->id, 'barmodel' => $bar->id]
      );
    $response = $this->delete($uri);
    $response->assertStatus(204/** NO CONTENT */);
    $this->assertNull(BarModel::find($bar->id));
  }


  /** @test */
  public function create_best_friend_has_one_subresource_of_foo() {
    $foo = FooModel::create(
      [
        'name'    => 'some foo',
        'user_id' => $this->loggedInUserId
      ],
    );
    $uri = route("api.foomodel.bestfriend.create", [$foo->id]);
    $args = ['name' => 'foo friend'];
    $response = $this->post($uri, $args);

    // confirm foreign key to parent resource is inferred from URL parameters
    $response->assertJson(array_merge($args, ['foo_model_id' => $foo->id]));
    $response->assertStatus(200);

    $foo->refresh();
    $this->assertInstanceOf(BestFriend::class, $foo->bestFriend);
  }

  /** @test */
  public function retrieve_best_friend_as_has_one_subresource_of_foo() {
    $foo = FooModel::create(
      [
        'name'    => 'some foo',
        'user_id' => $this->loggedInUserId
      ],
    );
    $foo->bestFriend()->save($bf = new BestFriend(['name' => 'foo-friend']));
    $uri = route("api.foomodel.bestfriend.retrieve", [$foo->id]);
    $response = $this->get($uri);

    // confirm foreign key to parent resource is inferred from URL parameters
    $response->assertJson(
      [
        'id'           => $bf->id,
        'name'         => 'foo-friend',
        'foo_model_id' => $foo->id
      ]
    );
    $response->assertStatus(200);
  }

  /** @test */
  public function update_best_friend_as_has_one_subresource_of_foo() {
    $foo = FooModel::create(
      [
        'name'    => 'some foo',
        'user_id' => $this->loggedInUserId
      ],
    );
    $foo->bestFriend()->save($bf = new BestFriend(['name' => 'foo-friend']));
    $uri = route("api.foomodel.bestfriend.update", [$foo->id]);
    $args = ['name' => 'new-friend'];
    $response = $this->patch($uri, $args);

    // confirm foreign key to parent resource is inferred from URL parameters
    $response->assertJson(
      [
        'id'           => $bf->id,
        'name'         => 'new-friend',
        'foo_model_id' => $foo->id
      ]
    );
    $response->assertStatus(200);
  }

  /** @test */
  public function delete_best_friend_as_has_one_subresource_of_foo() {
    $foo = FooModel::create(
      [
        'name'    => 'some foo',
        'user_id' => $this->loggedInUserId
      ],
    );
    $foo->bestFriend()->save($bf = new BestFriend(['name' => 'foo-friend']));

    // Delete API call
    $uri = route("api.foomodel.bestfriend.update", [$foo->id]);
    $response = $this->delete($uri);
    $response->assertStatus(204);

    // Confirm Model now missing relation
    $foo->refresh();
    $this->assertNull($foo->bestFriend);
  }

  /** @test */
  public function has_one_then_has_many_create_resource() {
    $foo =
      FooModel::create(
        ['name' => 'some foo', 'user_id' => $this->loggedInUserId]
      );
    $foo->bestFriend()->save($bf = new BestFriend(['name' => 'foo-friend']));
    $uri = route("api.foomodel.bestfriend.pets.create", [$foo->id]);
    $response = $this->post($uri, ['animal_type' => 'dog']);
    $response->assertStatus(200);
  }
}

