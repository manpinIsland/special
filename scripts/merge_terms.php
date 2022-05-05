<?php
/**
 * merge_terms
 *
 * This script takes a tab-delimited CSV of source and target taxonomy term
 * identifiers and updates references to the source term with references to 
 * the target term before deleting the source term.
 *
 * WARNING: ONLY the Authority Source URI field from the source term will be 
 * preserved! While incoming references are updated, OUTGOING references
 * will be lost on the merge. Copy any relevant field values from the source
 * term to the target term *before* merging.
 * 
 * Example CSV (only source_path and target_path are required):
 * source term label<tab>source_path<tab>target term label<tab>target_path
 * Old term<tab>/taxonomy/term/20724<tab>New Term<tab>/taxonomy/term/4249
 *
 * @usage: drush scr merge_terms.php -- <username> <file>
 */
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

# Now actually sanity-check rows and consolidate matches
$merges = [];
foreach ($csv as $line) {
  # Grab the "source" TID; the term to disappear.
  if(array_key_exists('source_path', $line) && preg_match('/\d+$/', $line['source_path'], $matches) ) {
    $path_tid = $matches[0];
  } else {
    print("Skipping row where source_path tid not found: ".print_r($line,TRUE)."\n");
    continue;
  }
  # Grab the "target" TID, the term we are keeping, and
  # add the source TID to the list of matches.
  if(array_key_exists('target_path', $line) && preg_match('/\d+$/', $line['target_path'], $matches) ) {
    $merges[$matches[0]][] = $path_tid;
  } else {
    print("Skipping row where target_path tid not found: ".print_r($line,TRUE)."\n");
    continue;
  }
}

$migrations = [
  'tematres',
  'as_agents_corp',
  'as_agents_family',
  'as_agents_people',
  'as_subjects'
];
$fields_to_update = [
  'node' => [
    'field_creator',
    'field_contributor',
    'field_interviewer',
    'field_narrator',
    'field_subject',
  ],
  'taxonomy_term' => [
    'field_creator',
    'field_relationships',
  ],
];
$db = \Drupal::service('database');
foreach ($merges as $keeper_tid => $duplicate_tids) {
  print("Merging tids ".implode(" ",$duplicate_tids)." into $keeper_tid:\n");
  # Migrations
  foreach ($migrations as $migration){
    # UPDATE "migrate_map_$migration"
    # SET destid1 = $keeper_tid
    # WHERE destid1 IN ($duplicate_tids);
    #$update = $db->update("migrate_map_$migration")
    #  ->fields(['destid1' => $keeper_tid])
    #  ->condition('destid1', $duplicate_tids, 'IN');
    $select = $db->select("migrate_map_$migration", 'mm')
      ->fields('mm', ['sourceid1','destid1'])
      ->condition('destid1', $duplicate_tids, 'IN');
    $results = $select->execute()->fetchAll();
    if ( count($results) > 0 ) {
      foreach ($results as $result) {
        print("\tchange $migration ".$result->sourceid1." from ".$result->destid1." to $keeper_tid\n");
      }

      # Let's do this...
      try {
        $update = $db->update("migrate_map_$migration")
          ->fields(['destid1' => $keeper_tid])
          ->condition('destid1', $duplicate_tids, 'IN');
        $update_result = $update->execute();
        print("\tUPDATE changed count($update_result) row(s) in migrate_map_$migration\n");
      }
      catch (\PDOException $e) {
        print("!! Could not run update for $migration from ".implode(" ",$duplicate_tids)." to $keeper_tid !!\n");
      }
    }
  }
  
  # Update incomming references
  foreach ($fields_to_update as $type => $fields){
    foreach ($fields as $field) {
      $select = $db->select("${type}__${field}", 'nf')
        ->fields('nf', ['bundle','entity_id', "${field}_target_id"])
        ->condition("${field}_target_id", $duplicate_tids, 'IN');
      $results = $select->execute()->fetchAll();
      if ( count($results) > 0 ) {
        foreach ($results as $result) {
          $resultArr = (array) $result;
          print("\tchanging $field target_id for ".$resultArr['entity_id']." (".$result->bundle .") from ".$resultArr["${field}_target_id"]." to $keeper_tid\n");
        }
        try {
          $update = $db->update("${type}__${field}")
            ->fields(["${field}_target_id" => $keeper_tid])
            ->condition("${field}_target_id", $duplicate_tids, 'IN');
          $update_result = $update->execute();
          print("\tUPDATE changed $update_result row(s) in ${type}__${field}\n");
        }
        catch (\PDOException $e) {
          print("!! Could not run update ${field}_target_id from ".implode(" ",$duplicate_tids)." to $keeper_tid !!\n");
        }
      }
    }
  }
  
  # Update taxonomy_index
  $ti_update = $db->update("taxonomy_index")
    ->fields(['tid' => $keeper_tid])
    ->condition('tid', $duplicate_tids, 'IN');
  $ti_update_count = $ti_update->execute();
  print("\tUPDATE changed $ti_update_count taxonomy_index references\n");

  # Copy URIs and Purge duplicate.
  $keeper = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($keeper_tid);
  $keeper_uris = array_map(fn($link): string => $link['uri'], $keeper->field_authority_link->getValue());
  foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($duplicate_tids) as $dup) {
    # Copy over field_authority_link, if there is one, before deleting.
    foreach ($dup->field_authority_link->getValue() as $key => $link) {
      if (!in_array($link['uri'], $keeper_uris)) {
        $keeper->field_authority_link[] = $link;
	$keep_uris[] = $link['uri'];
	print("\tAdding uri ".$link['uri']." to $keeper_tid.\n");
      }
    }
    print("\tDELETING ".$dup->id()."!\n");
    $dup->delete();
  }
}
