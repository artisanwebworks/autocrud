<?php


namespace ArtisanWebworks\AutoCrud\Test\Fixtures;

use ArtisanWebworks\AutoCrud\ValidatingModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ArtisanWebworks\AutoCrud\Test\Fixtures\Pet;

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


  // ---------- RELATIONS ---------- //

  protected $with = ['pets'];

  public function pets(): HasMany {
    return $this->hasMany(Pet::class);
  }

}

