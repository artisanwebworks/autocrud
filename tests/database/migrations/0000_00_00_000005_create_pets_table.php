<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePetsTable extends Migration {

  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create('pets', function (Blueprint $table) {
      $table->bigIncrements('id')->startingValue(10000000);
      $table->string('animal_type');
      $table->timestamps();

      $table->unsignedBigInteger('best_friend_id');
      $table
        ->foreign('best_friend_id')
        ->references('id')->on('best_friends')
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
    Schema::dropIfExists('pets');
  }
}