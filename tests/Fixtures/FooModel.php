<?php

namespace ArtisanWebworks\AutoCRUD\Test\Fixtures;

use ArtisanWebworks\AutoCRUD\ValidatingModel;

class FooModel extends ValidatingModel {

  protected $fillable = ['name'];
  protected $rules = ['name' => 'string|required|min:2'];
  protected $messages = [
      'min' => ':attribute must be at least :min characters',
//      'same'    => 'The :attribute and :other must match.',
//      'size'    => 'The :attribute must be exactly :size.',
//      'between' => 'The :attribute must be between :min - :max.',
//      'in'      => 'The :attribute must be one of the following types: :values',
    ];

}