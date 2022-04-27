<?php

$query = \Drupal::entityQuery('node');
$query->condition('type', 'dc_object', '=');
$nids = $query->execute();
$path_alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');

foreach ($nids as $nid) {
  $actual_alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $nid);
  print("Checking node/$nid ($actual_alias)\n");
  // Load all path alias for this node.
  $alias_objects = $path_alias_storage->loadByProperties([
    'path' => '/node/' . $nid,
  ]);

  // Delete all other alias than the actual one.
  foreach ($alias_objects as $alias_object) {
    if ($alias_object->get('alias')->value !== $actual_alias) {
      print("\tDeleting bad alias ".$alias_object->get('alias')->value."\n");
      $alias_object->delete();
    }
  }
  // Load all new path alias for this node.
  $new_alias_objects = $path_alias_storage->loadByProperties([
    'path' => '/node/' . $nid,
  ]);
  // Delete duplicate aliases.
  if (count($new_alias_objects) > 1) {
    array_shift($new_alias_objects);
    foreach ($new_alias_objects as $alias_object) {
      $alias_object->delete();
    }
  }
}
