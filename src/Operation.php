<?php

namespace ArtisanWebworks\AutoCrud;

abstract class Operation {
  const CREATE = "create";
  const RETRIEVE = "retrieve";
  const RETRIEVE_ALL = "retrieve-all";
  const UPDATE = "update";
  const DELETE = "delete";
}