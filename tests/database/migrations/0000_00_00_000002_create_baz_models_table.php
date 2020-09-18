<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBazModelsTable extends Migration {

  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create('baz_models', function (Blueprint $table) {
      $table->bigIncrements('id');
      $table->boolean('can-recognize')->default(false);
      $table->timestamps();
      
      $table->unsignedBigInteger('bar_model_id');
      $table
        ->foreign('bar_model_id')
        ->references('id')->on('bar_models')
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
    Schema::dropIfExists('baz_models');
  }
}