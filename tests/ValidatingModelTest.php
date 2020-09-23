<?php


namespace ArtisanWebworks\AutoCRUD\Test;

// Internal
use ArtisanWebworks\AutoCRUD\Test\Fixtures\BarModel;
use ArtisanWebworks\AutoCRUD\Test\Fixtures\FooModel;
use Illuminate\Validation\ValidationException;

class ValidatingModelTest extends TestBase {

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

  /** @test */
  public function foo_has_many_bar() {
    $foo = FooModel::create(['name' => 'fubar', 'user_id' => 1]);
    $bar1 = BarModel::create(['level' => 1, 'foo_model_id' => $foo->id]);
    $bar2 = BarModel::create(['level' => 2, 'foo_model_id' => $foo->id]);
    $this->assertCount(2, $foo->barModels);
  }
}