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
  | For example if we have users, which have many posts, which have many comments,
  | which have many responses, a depth of 1 will expose...
  |
  |   api/users/i/posts
  |
  | But NOT...
  |
  |   api/users/i/posts/j/comments
  |
  | A depth 3 on the other hand, will expose the full relation lineage...
  |
  |   api/users/i/posts/j/comments/k/responses
  |
  */

  'recursion-depth' => 4,

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
    ['user-id-property' => 'id', 'model' => 'App\\Model\\User'],
    ['user-id-property' => 'id', 'model' => 'App\\User'],

    // Further, if we encounter a 'user_id' property on any resource,
    // we will use it to accept or reject access.
    ['user-id-property' => 'user_id']

  ]


];
