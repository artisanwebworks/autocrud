<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use ArtisanWebworks\AutoCrud\Test\Fixtures\DBHelper;

class CreateBarModelsTable extends Migration {

  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create('bar_models', function (Blueprint $table) {
      $table->id();
      $table->integer('level');
      $table->timestamps();

      $table->unsignedBigInteger('foo_model_id')->nullable();
      $table
        ->foreign('foo_model_id')
        ->references('id')->on('foo_models')
        ->onDelete("cascade");
    });

    DBHelper::setIdStartValue('bar_models', 2000, ['level' => 1]);
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::dropIfExists('bar_models');
  }
}