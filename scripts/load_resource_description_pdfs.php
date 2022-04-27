<?php

$action = \Drupal::entityTypeManager()->getStorage('action')->load(\Drupal::config('archivesspace.settings')->get('resource_description_files.pdf_generate_action'));
$archival_resources = \Drupal::entityQuery('node')->condition('type', 'archival_resource')->notExists('field_printable_pdf');
$results = $archival_resources->execute();
foreach ($results as $nid) {
  $node = \Drupal::entityTypeManager()->getStorage('node')->load(intval($nid));
  print("Processing ".$node->title->value." (".$node->field_resource_identifier->value.")");
  print("\t executing action...");
  try {
    $action->execute([$node]);
    print("\t complete!");
  } catch (Exception $e) {
    print("\t FAILED! ".$e->getMessage());
  }
  print("\n");
  #print("\tSleeping for half a second...\n");
  #time_nanosleep(0, 500000000);
}
