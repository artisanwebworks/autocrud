<?php


namespace ArtisanWebworks\AutoCRUD\Test\Fixtures;

use ArtisanWebworks\AutoCRUD\ValidatingModel;

class BazModel extends ValidatingModel {
  protected $fillable = ['can-recognize', 'bar_model_id'];
}