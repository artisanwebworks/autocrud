<?php


namespace ArtisanWebworks\AutoCrud\Test\Fixtures;

use ArtisanWebworks\AutoCrud\ValidatingModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarModel extends ValidatingModel {
  protected $fillable = ['level', 'foo_model_id'];

  public function bazModels(): HasMany {
    return $this->hasMany(BazModel::class);
  }

  // ---------- EVENTS ---------- //

  protected static function booted() {

    // Automatically create a bar relation on create
    // (so we can confirm it is automatically expanded in
    // the API create result)
    static::created(
      function ($foo) {

        // To test transactional behavior, throw an exception after creation
        // with level 13
        if ($foo->level === 13) {
          throw new \Exception();
        }

      }
    );
  }
}