<?php

namespace ArtisanWebworks\AutoCRUD\Test\Fixtures;

use ArtisanWebworks\AutoCRUD\ValidatingModel;
use ArtisanWebworks\AutoCRUD\Test\Fixtures\FooModel;

use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends ValidatingModel {

  protected $fillable = [
    'username',
  ];

  public function foomodels(): HasMany {
    return $this->hasMany(FooModel::class);
  }

}