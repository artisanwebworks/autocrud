<?php


namespace ArtisanWebworks\AutoCrud\Test\Fixtures;

use ArtisanWebworks\AutoCrud\ValidatingModel;

class BazModel extends ValidatingModel {
  protected $fillable = ['can-recognize', 'bar_model_id'];
}