<?php

if (! require 'fedora_user_switch.php') { return; }

# Load into array of associative arrays so we can check for 
# column name instead of position.
$file = $extra[1];
if (!is_readable($file)) { print("Can't read file path: ".$file."\n"); return; }
$csv = array_map(function($v){return str_getcsv($v, "\t");}, file($file));
array_walk($csv, function(&$a) use ($csv) {
  if (count($csv[0]) != count($a)) { 
    print("WARNING: CSV headers count do not match the columns in at least one row.\n"); 
  }
  $count = min(count($csv[0]), count($a));
  $a = array_combine(array_slice($csv[0], 0, $count), array_slice($a, 0, $count));
});
array_shift($csv); # remove column header

$media_storage = \Drupal::entityTypeManager()->getStorage('media');
$db = \Drupal::service('database');
$current_migration = '';
$migration_map = [];
foreach ($csv as $line) {
  # Load a new migration map on change in migration.
  if ($line['migration'] !== $current_migration) {
    $current_migration = $line['migration'];
    $results = $db->select("migrate_map_{$line['migration']}", 'mm')
      ->fields('mm', ['sourceid1','destid1'])->execute();
    $migration_map = $results->fetchAllKeyed();
  }
  if($migration_map[$line['file_path']] && $media = $media_storage->load($migration_map[$line['file_path']])) {
    print("Setting media ".$media->id()."\t'".$line['file_path']."' to ".$line['preservation_level']."\n");
    $media->set('field_preservation_level', $line['preservation_level']);
    try {
      $media->save();
    }
    catch (Exception $ex) {
      print("Could not save the media: ".$ex->getMessage());
    }
  }
}
