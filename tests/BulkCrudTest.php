<?php


namespace ArtisanWebworks\AutoCrud\Test;


use ArtisanWebworks\AutoCrud\GenericAPIController;
use ArtisanWebworks\AutoCrud\Test\Fixtures\FooModel;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class BulkCrudTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(FooModel::class);

    static::printRoutes();
  }

  /** @test */
  public function bulk_create_root_resource_is_disabled() {
    $this->expectException(RouteNotFoundException::class);
    $uri = route('api.foomodel.bulk-create');
  }

  /** @test */
  public function bulk_create_sub_resource_bar_models() {

    $foo = FooModel::create(['name' => 'root foo', 'user_id' => $this->loggedInUserId]);
    $uri = route('api.foomodel.barmodels.bulk-create', ['foomodel' => $foo->id]);
    $args = [
      ['level' => 1], // foo_model fk should be inferred from url
      ['level' => 2],
      ['level' => 3],
    ];
    $response = $this->post($uri, $args);
    $response->assertStatus(200 /** OK */);
  }
}