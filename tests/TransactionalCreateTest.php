<?php


use ArtisanWebworks\AutoCrud\GenericAPIController;
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
    $SPECIAL_TRIGGER_NAME = "exploding foo";
    $initialFooCount = FooModel::count();
    $uri = route('api.foomodel.create');
    $args = ['name' => $SPECIAL_TRIGGER_NAME, 'user_id' => $this->loggedInUserId];
    $response = $this->post($uri, $args);
    $response->assertStatus(500);
    $this->assertSame(FooModel::count(), $initialFooCount);
  }

}