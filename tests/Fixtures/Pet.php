<?php


namespace ArtisanWebworks\AutoCrud\Test\Fixtures;

use ArtisanWebworks\AutoCrud\ValidatingModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pet extends ValidatingModel {

  protected $fillable = [
    'animal_type',
    'best_friend_id'
  ];

}