<?php


return [

  /*
  |--------------------------------------------------------------------------
  | Route Recursion Depth
  |--------------------------------------------------------------------------
  |
  | Determines how deeply to inspect Eloquent Model relations
  | to expose sub-resource routes.
  |
  | For example if we have Foo, which has many Bar, which has many Baz,
  | and recurse to a depth of 1, we will expose
  |
  | api/foo/{id}/bars
  |
  | But NOT
  |
  | api/foo/{id}/bar/{id}/bazs
  |
  */

  'recursion-depth' => 1,

  /*
  |--------------------------------------------------------------------------
  | Auth-User Access Rules
  |--------------------------------------------------------------------------
  |
  | The generated API will only permit CRUD operations on resources for which
  | there is implied ownership to the session's auth user.

  | Ownership is established when *any* ownership rule is satisfied anywhere on
  | the relation-chain.
  |
  | An ownership rule definition specifies a property that must be present on
  | the a resource's Eloquent model and equal to the auth user's id, and
  | optionally, further specifies a specific model class.
  |
  */

  'access-rules' => [

    // If the sub-resource stems from a User-based resource, we expect
    // the user id passed in the route to be equal to the logged in user
    ['property' => 'id', 'model' => 'App\\Model\\User'],
    ['property' => 'id', 'model' => 'App\\User'],

    // Further, if we encounter a 'user_id' property on any resource,
    // we will use it to determine access.
    ['property' => 'user_id']

  ]


];
