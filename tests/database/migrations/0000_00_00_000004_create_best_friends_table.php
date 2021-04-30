<?php

use ArtisanWebworks\AutoCrud\Test\Fixtures\DBHelper;
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
      $table->id();
      $table->string('name');
      $table->timestamps();

      $table->unsignedBigInteger('foo_model_id')->nullable();
      $table
        ->foreign('foo_model_id')
        ->references('id')->on('foo_models')
        ->onDelete("cascade");
    });

    DBHelper::setIdStartValue('best_friends', 4000, ['name' => 'aaa']);
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