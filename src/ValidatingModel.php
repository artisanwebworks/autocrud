<?php
namespace ArtisanWebworks\AutoCRUD;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;

abstract class ValidatingModel extends Model {

  protected $fillable = [];
  protected $rules = [];
  protected $messages = [];


  /**
   * @throws ValidationException
   */
  public function validate() {

    echo "calling validation\n";

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
      echo "\nvalidation result\n";
      var_dump($model->validate());
    };
    static::creating($handler);
    static::updating($handler);
  }
}
