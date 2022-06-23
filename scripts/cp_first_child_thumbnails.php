<?php

/**
 * @file
 * Copies thumbnail of complex object's first child to parent.
 */

$islandora_utils = \Drupal::service('islandora.utils');

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$media_storage = \Drupal::entityTypeManager()->getStorage('media');

$thumbnail_term = $islandora_utils->getTermForUri('http://pcdm.org/use#ThumbnailImage');

# Find thumbnails.
$sub_query = \Drupal::database()->select('media', 'm');
$sub_query->join('media__field_media_use', 'use', 'm.mid = use.entity_id');
$sub_query->join('media__field_media_of', 'of', 'm.mid = of.entity_id');
$sub_query->fields('of', ['field_media_of_target_id'])
  ->condition('use.field_media_use_target_id', $thumbnail_term->id());

# Find digital object parent records w/o thumbnails.
$parent_q = \Drupal::database()->select('node__field_member_of', 'mo');
$parent_q->join('node', 'p', 'mo.field_member_of_target_id = p.nid');
$parent_q->join('node', 'n', 'mo.entity_id = n.nid');
$parent_q->fields('mo', ['field_member_of_target_id'])
  ->condition('field_member_of_target_id', '', '<>')
  ->condition('n.type', 'dc_object', '=')
  ->condition('p.type', 'dc_object', '=')
  ->condition('p.nid', $sub_query, 'NOT IN')
  ->distinct();

$parent_ids = $parent_q->execute()->fetchCol();

foreach ($parent_ids as $parent_id) {
  $parent = $node_storage->load($parent_id);

  // Find first child.
  $child_query = \Drupal::entityQuery('node')->condition('type', 'dc_object')->condition('field_member_of', $parent_id)->sort('field_weight', 'ASC')->range(0, 1);
  $query_result = $child_query->execute();
  $child = $node_storage->load(reset($query_result));
  print("parent " . $parent->id() . " -> child " . $child->id() . "\n");
  
  // Get child thumbnail.
  $thumbnail = $islandora_utils->getMediaWithTerm($child, $thumbnail_term);
  if (empty($thumbnail->field_media_image)) {
    print("\tChild has no thumbnail file!\n");
    continue;
  }
  // Copy child thumbnail.
  try {
    $thumbnail->field_media_image->first()->entity->getFileUri();
    $new_th_path = str_replace($child->id(), $parent->id(), $thumbnail->field_media_image->first()->entity->getFileUri());
    $new_file = file_copy($thumbnail->field_media_image->first()->entity, $new_th_path);
    $new_file->save();

    $new_thumbnail = $thumbnail->createDuplicate();
    $new_name = str_replace($child->id(), $parent->id(), $new_thumbnail->name->first()->value);
    $new_thumbnail->set('name', $new_name);
    $new_thumbnail->set('field_media_of', [$parent]);
    $new_thumbnail->set('field_media_image', [$new_file]);
    $new_thumbnail->save();
  }
  catch (Throwable $t) {
    print("Skiping " . $parent->id() . " due to: " . $t->getMessage() . "\n");
  }
}
