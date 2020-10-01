<?php


namespace ArtisanWebworks\AutoCrud;


use Illuminate\Support\ServiceProvider;

class AutoCrudServiceProvider extends ServiceProvider {

  public function register() {
    $this->mergeConfigFrom(
      __DIR__ . '/config/auto-crud.php',
      'auto-crud'
    );
  }

  public function boot() {
    $this->publishes(
      [
        __DIR__ . '/config/auto-crud.php' => config_path('auto-crud.php')
      ]
    );
  }

}