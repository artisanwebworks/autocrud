<?php


use ArtisanWebworks\AutoCrud\GenericAPIController;
use ArtisanWebworks\AutoCrud\Test\Fixtures\BarModel;
use ArtisanWebworks\AutoCrud\Test\Fixtures\FooModel;
use ArtisanWebworks\AutoCrud\Test\TestBase;
use Illuminate\Support\Facades\DB;

class TransactionalCreateTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();
    GenericAPIController::declareRoutes(FooModel::class);
  }

  /** @test */
  public function exception_in_foo_created_hook_rolls_back_create() {
    $foo =
      FooModel::create(
        ['name' => 'some foo', 'user_id' => $this->loggedInUserId]
      );
    $uri = route("api.foomodel.barmodels.create", [$foo->id]);
    $args = ['level' => 13 /** level 13 triggers an exception */];
    $initialBarCount = BarModel::count();
    $response = $this->post($uri, $args);
    $response->assertStatus(500);
    $this->assertSame($initialBarCount, BarModel::count());
  }

}