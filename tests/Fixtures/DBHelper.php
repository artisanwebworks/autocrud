<?php

namespace ArtisanWebworks\AutoCrud\Test\Fixtures;

use Illuminate\Support\Facades\DB;

class DBHelper {

  public static function setIdStartValue($table, $value, $createArgs = []) {
    $id = $value - 1;
    $args = array_merge($createArgs, ['id' => $id]);
    DB::table($table)->insert($args);
    DB::table($table)->where('id', $id)->delete();
  }

}