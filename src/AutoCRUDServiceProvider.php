<?php


namespace ArtisanWebworks\AutoCRUD;


use Illuminate\Support\ServiceProvider;

class AutoCRUDServiceProvider extends ServiceProvider {

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