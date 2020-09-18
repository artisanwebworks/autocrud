<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBarModelsTable extends Migration {

  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create('bar_models', function (Blueprint $table) {
      $table->bigIncrements('id');
      $table->integer('level');
      $table->timestamps();
      
      $table->unsignedBigInteger('foo_model_id');
      $table
        ->foreign('foo_model_id')
        ->references('id')->on('foo_models')
        ->onDelete("cascade");
    });
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