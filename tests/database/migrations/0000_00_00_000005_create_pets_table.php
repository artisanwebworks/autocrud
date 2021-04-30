<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use ArtisanWebworks\AutoCrud\Test\Fixtures\DBHelper;

class CreatePetsTable extends Migration {

  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create('pets', function (Blueprint $table) {
      $table->id();
      $table->string('animal_type');
      $table->timestamps();

      $table->unsignedBigInteger('best_friend_id')->nullable();
      $table
        ->foreign('best_friend_id')
        ->references('id')->on('best_friends')
        ->onDelete("cascade");

    });

    DBHelper::setIdStartValue('pets', 5000, ['animal_type' => 'aaa']);
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