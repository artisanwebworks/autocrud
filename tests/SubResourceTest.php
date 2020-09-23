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

class SubResourceTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(FooModel::class);

    // Mock the the logged in user id return from Auth Facade
    $loggedInUser = User::create(['username' => 'mr mock']);
    Auth::shouldReceive('id')->andReturn($loggedInUser);

    // Seed starting DB state for all tests
    $firstFoo = FooModel::create(['name' => 'some foo']); // ID = 1
    $bar1 = BarModel::create(['level' => 1, 'foo_model_id' => $firstFoo->id]);
    $bar2 = BarModel::create(['level' => 2, 'foo_model_id' => $firstFoo->id]);

    FooModel::create(['name' => 'some other foo']); // ID = 2
  }
}