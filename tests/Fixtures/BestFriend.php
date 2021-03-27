<?php


namespace ArtisanWebworks\AutoCrud\Test\Fixtures;

use ArtisanWebworks\AutoCrud\ValidatingModel;

/**
 * Class BestFriend - HasOne relation of FooModel.
 *
 * @package ArtisanWebworks\AutoCrud\Test\Fixtures
 */
class BestFriend extends ValidatingModel {
  protected $fillable = [
    'name',
    'foo_model_id'
  ];
}