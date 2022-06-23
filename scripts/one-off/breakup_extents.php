<?php
/**
 * break-extents
 *
 * This script finds items with extent statements that are a single
 * field delimited by semicolons and then breaks that single value
 * into multiple extent field values.
 *
 * @usage: drush scr break-extents.php -- <username>
 */
require 'fedora_user_switch.php';

$query = \Drupal::entityQuery('node')->condition('field_extent', '%;%', 'LIKE')->accessCheck(FALSE);
foreach ($query->execute() as $nid) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    print($node->id() . "\t" . $node->field_digital_id->value . "\t". $node->field_extent->value."\n");
    #print(print_r(array_map(fn($value): string => trim($value), explode(';', $node->field_extent->value)),TRUE)."\n");
    $extents = array_map(fn($value): string => trim($value), explode(';', $node->field_extent->value));
    #print(print_r($extents,TRUE)."\n");
    $node->set('field_extent', $extents);
    $node->save();
}
