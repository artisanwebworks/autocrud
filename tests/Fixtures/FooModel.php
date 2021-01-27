<?php

namespace ArtisanWebworks\AutoCrud\Test\Fixtures;

use ArtisanWebworks\AutoCrud\ValidatingModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

function customValidator($attribute, $value, $fail) {
  if ($value === 'foo') {
    $fail('The '.$attribute.' is invalid.');
  }
}

class FooModel extends ValidatingModel {

  protected $fillable = ['name', 'user_id'];

  //  protected $casts = ['user_id' => 'int'];

  protected array $rules = [
    'name' => [
      'string',
      'required',
      'min:3'
      ]
    ];

  protected array $messages = [
    'min' => ':attribute must be at least :min characters'

    //      'same'    => 'The :attribute and :other must match.',
    //      'size'    => 'The :attribute must be exactly :size.',
    //      'between' => 'The :attribute must be between :min - :max.',
    //      'in'      => 'The :attribute must be one of the following types: :values',
  ];

  public function __construct(array $attributes = []) {
    parent::__construct($attributes);

    $this->rules['name'][] = function ($attribute, $value, $fail) {

      $args = func_get_args();

      if ($value === "fooo") {
        $fail("no");
      }
    };
  }

  // ---------- RELATIONS ---------- //

  protected $with = ['barModels'];

  public function barModels(): HasMany {
    return $this->hasMany('ArtisanWebworks\AutoCrud\Test\Fixtures\BarModel');
  }


  // ---------- EVENTS ---------- //

  protected static function booted() {

    // Automatically create a bar relation on create
    // (so we can confirm it is automatically expanded in
    // the API create result)
    static::created(
      function ($foo) {
        $foo->barModels()->save(new BarModel(['level' => 0]));
      }
    );
  }

  }