<?php


namespace ArtisanWebworks\AutoCrud\Test;


use ArtisanWebworks\AutoCrud\GenericAPIController;
use ArtisanWebworks\AutoCrud\Test\Fixtures\User;
use ArtisanWebworks\AutoCrud\Test\Fixtures\FooModel;
use ArtisanWebworks\AutoCrud\Test\Fixtures\BarModel;
use ArtisanWebworks\AutoCrud\Test\Fixtures\BazModel;

class LineageVerificationTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(User::class);

//    static::printRoutes();
  }

  /** @test */
  public function subresource_returns_error_when_ancestor_exists_but_not_related() {
    $relatedFoo = FooModel::create(['name' => 'related foo', 'user_id' => $this->loggedInUserId]);
    $unrelatedFoo = FooModel::create(['name' => 'stranger foo', 'user_id' => $this->loggedInUserId]);
    $bar = $relatedFoo->barModels()->save(BarModel::make(['level' => 1]));
    $baz = $bar->bazModels()->save(BazModel::make(['can-recognize' => false]));

    // users/{user}/foomodels/{foomodel}/barmodels/{barmodels}/bazmodels/{bazmodel}
    $uri = route("api.users.foomodels.barmodels.bazmodels.retrieve", [
      'user' => $this->loggedInUserId,
      'foomodel' => $unrelatedFoo->id,
      'barmodel' => $bar->id, // not actually related to foomodel
      'bazmodel' => $baz->id
    ]);
    $response = $this->get($uri);
    $response->assertStatus(403);
  }

  /** @test */
  public function subresource_returns_error_when_ancestor_does_not_exist() {
    $foo = FooModel::create(['name' => 'related foo', 'user_id' => $this->loggedInUserId]);
    $bar = $foo->barModels()->save(BarModel::make(['level' => 1]));
    $baz = $bar->bazModels()->save(BazModel::make(['can-recognize' => false]));

    // users/{user}/foomodels/{foomodel}/barmodels/{barmodels}/bazmodels/{bazmodel}
    $uri = route("api.users.foomodels.barmodels.bazmodels.retrieve", [
      'user' => $this->loggedInUserId,
      'foomodel' => 13, // doesn't exist
      'barmodel' => $bar->id,
      'bazmodel' => $baz->id
    ]);
    $response = $this->get($uri);
    $response->assertStatus(403);
  }

}