<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBestFriendsTable extends Migration {

  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create('best_friends', function (Blueprint $table) {
      $table->bigIncrements('id')->startingValue(1000000);
      $table->string('name');
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
    Schema::dropIfExists('best_friends');
  }
}