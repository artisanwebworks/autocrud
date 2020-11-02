<?php


namespace ArtisanWebworks\AutoCrud\Test;


use Illuminate\Support\Facades\Artisan;

class InspectRelationsTest extends TestBase {

  /** @test */
  public function js_file_generated() {
    Artisan::call("autocrud:inspect-relations");
  }
}