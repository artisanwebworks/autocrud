<?php


namespace ArtisanWebworks\AutoCRUD\Test;


class EndpointValidationTest extends TestBase {

  /** @test */
  public function update_with_validation_rule_violation_returns_error() {
    $uri = route('api.foomodel.update', ['id' => 1]);
    $response = $this->patch($uri, ['name' => 'fu']);
    $response->assertJson(
      ['errors' => ['name' => 'name must be at least 3 characters']]
    );
    $response->assertStatus(422/** UNPROCESSABLE ENTITY */);
  }

  /** @test */
  public function update_with_extraneous_argument_returns_error() {
    $uri = route('api.foomodel.update', ['id' => 1]);
    $response = $this->patch($uri, ['name' => 'updated foo', 'bar' => 'baz']);
    $response->assertJson(['errors' => ['bar is an unrecognized field']]);
  }

  /** @test */
  public function update_with_undefined_id_returns_error() {
    $uri = route('api.foomodel.update', ['id' => 777]);
    $response = $this->patch($uri, ['name' => 'updated foo']);
    $response->assertJson(['errors' => ['777 is not a valid foomodel id']]);
  }

  /** @test */
  public function retrieve_invalid_id_returns_error() {
    $uri = route('api.foomodel.retrieve', ['id' => 777]);
    $response = $this->get($uri);
    $response->assertJson(['errors' => ['777 is not a valid foomodel id']]);
    $response->assertStatus(404);
  }

}