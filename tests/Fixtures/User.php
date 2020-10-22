<?php

namespace ArtisanWebworks\AutoCrud\Test\Fixtures;

use ArtisanWebworks\AutoCrud\ValidatingModel;
use ArtisanWebworks\AutoCrud\Test\Fixtures\FooModel;

use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends ValidatingModel {

  protected $fillable = [
    'username',
  ];


  // ---------- RELATIONS ---------- //

  protected $with = ['fooModels'];

  public function fooModels(): HasMany {
    return $this->hasMany(FooModel::class);
  }

}