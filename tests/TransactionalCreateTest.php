<?php


use ArtisanWebworks\AutoCrud\GenericAPIController;
use ArtisanWebworks\AutoCrud\Test\Fixtures\FooModel;
use ArtisanWebworks\AutoCrud\Test\TestBase;

class TransactionalCreateTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();
    GenericAPIController::declareRoutes(FooModel::class);
  }

  /** @test */
  public function exploding_foo_rolled_back() {
    $SPECIAL_TRIGGER_NAME = "exploding foo";
    $initialFooCount = FooModel::count();
    $uri = route('api.foomodel.create');
    $args = ['name' => $SPECIAL_TRIGGER_NAME, 'user_id' => $this->loggedInUserId];
    $response = $this->post($uri, $args);
    $response->assertStatus(500);
    $this->assertSame(FooModel::count(), $initialFooCount);
  }

}