<?php


namespace ArtisanWebworks\AutoCRUD\Test\Fixtures;

use ArtisanWebworks\AutoCRUD\ValidatingModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarModel extends ValidatingModel {
  protected $fillable = ['level', 'foo_model_id'];

  public function bazModels(): HasMany {
    return $this->hasMany(BazModel::class);
  }

}