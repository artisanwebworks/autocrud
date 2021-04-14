<?php


namespace ArtisanWebworks\AutoCrud\Test;


use ArtisanWebworks\AutoCrud\GenericAPIController;
use ArtisanWebworks\AutoCrud\Test\Fixtures\User;
use ArtisanWebworks\AutoCrud\Test\Fixtures\FooModel;

class ExpandedJsonResponseTest extends TestBase {

  protected function setUp(): void {
    parent::setUp();

    // Declare routes
    GenericAPIController::declareRoutes(User::class);

    static::printRoutes();
  }

  /** @test */
  public function user_foomodels_relation_expanded_in_retrieve_response() {

    User::find($this->loggedInUserId)->fooModels()->save(
      FooModel::make( $fooArgs =
        [
          'name' => 'related foo'
        ]
      )
    );

    $uri = route(
      "api.user.retrieve",
      [
        'user'     => $this->loggedInUserId,
      ]
    );

    $response = $this->get($uri);
    $response->assertJson(['foo_models' => [$fooArgs]]);
  }

  /** @test */
  public function foo_auto_generated_bar_relation_expanded_in_create_response() {
    $uri = route(
      "api.user.foomodels.create",
      [
        'user'     => $this->loggedInUserId,
      ]
    );

    // FooModel created() event creates one barModels relation,
    // which should be included in create result.
    $fooArgs = ['name' => 'new foo'];
    $response = $this->post($uri, $fooArgs);

    $response->assertJson(['bar_models' => [['level' => 0]]]);
  }
  
  /** @test */
  public function foo_bulk_create_response_expands_auto_generated_relations() {

    $uri = route('api.user.foomodels.bulk-create', [
      'user' => $this->loggedInUserId
     ]);
    $argsSet = [
      ['name' => 'foo1'],
      ['name' => 'foo2'],
      ['name' => 'foo3'],
    ];
    $response = $this->post($uri, $argsSet);

    // bar_models should be automatically populated
    foreach ($argsSet as $i => $args) {
      $argsSet[$i]['bar_models'] = [['level' => 0]];
    }

    $response->assertJson($argsSet);
    $response->assertStatus(200 /** OK */);
  }
}