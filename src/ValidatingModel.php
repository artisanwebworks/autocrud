<?php
namespace ArtisanWebworks\AutoCrud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

abstract class ValidatingModel extends Model {

  protected array $rules = [];
  protected array $messages = [];

  /**
   * @throws ValidationException
   */
  public function validate() {

    if ($this->rules) {
      Validator::make($this->toArray(), $this->rules, $this->messages)->validate();
    }

  }

  /**
   * Register update and create event handlers that validate model state against
   * validation rules specified on the model.
   */
  public static function boot() {
    parent::boot();

    $handler = function(ValidatingModel $model) {
      $model->validate();
    };

    static::creating($handler);
    static::updating($handler);
  }
}
