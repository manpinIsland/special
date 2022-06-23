<?php

/**
$query = \Drupal::entityQuery('node');
$query->condition('type', 'dc_object', '=');
$nids = $query->execute();
**/
$path_alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$sub_query = \Drupal::database()->select('path_alias', 'sp');
$sub_query->fields('sp',['alias']);
$sub_query->condition('sp.alias', '/ark:/62930/d1%', 'LIKE');
$sub_query->groupBy('sp.alias')->having('count(distinct(path)) > 1');

$dup_path_query = \Drupal::database()->select('path_alias', 'p');
$dup_path_query->fields('p',['path']);
$dup_path_query->condition('p.alias', $sub_query, 'IN');
#$dup_path_query->range(0,10);
$paths = $dup_path_query->execute()->fetchCol();
foreach ($paths as $path) {
  $actual_alias = \Drupal::service('path_alias.manager')->getAliasByPath($path);
  print("Checking $path ($actual_alias)\n");
  // Load all path alias for this node.
  $alias_objects = $path_alias_storage->loadByProperties([
    'path' => $path,
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
