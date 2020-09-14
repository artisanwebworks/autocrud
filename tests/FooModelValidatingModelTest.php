<?php


namespace ArtisanWebworks\AutoCRUD\Test;

// Internal
use ArtisanWebworks\AutoCRUD\Test\Fixtures\FooModel;
use Illuminate\Validation\ValidationException;

class FooModelValidatingModelTest extends BaseAutoCRUDTest {

  /** @test */
  public function rule_violation_throws_exception_on_create() {
    try {
      FooModel::create(['name' => 'a']);
      $this->fail("expected ValidationException");
    } catch (ValidationException $e) {
      $this->assertEquals(
        "name must be at least 3 characters",
        $e->validator->getMessageBag()->get('name')[0]
      );
    }
  }
}