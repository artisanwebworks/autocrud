<?php

use ArtisanWebworks\AutoCrud\Test\Fixtures\DBHelper;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFooModelsTable extends Migration {

  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create('foo_models', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->timestamps();

      $table->unsignedBigInteger('user_id')->nullable();
      $table
        ->foreign('user_id')
        ->references('id')->on('users')
        ->onDelete("cascade");
    });

    DBHelper::setIdStartValue('foo_models', 1000, ['name' => 'aaa']);
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::dropIfExists('foo_models');
  }

}