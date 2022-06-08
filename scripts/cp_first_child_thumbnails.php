<?php

/**
 * @file
 * Copies thumbnail of complex object's first child to parent.
 */

$islandora_utils = \Drupal::service('islandora.utils');

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$media_storage = \Drupal::entityTypeManager()->getStorage('media');

$thumbnail_term = $islandora_utils->getTermForUri('http://pcdm.org/use#ThumbnailImage');

$parent_q = \Drupal::database()->select('node__field_member_of', 'mo');
$parent_q->join('node', 'n', 'mo.field_member_of_target_id = n.nid');
$parent_q->fields('mo', ['field_member_of_target_id'])
  ->condition('field_member_of_target_id', '', '<>')
  ->condition('n.type', 'dc_object', '=')
  ->distinct();
$parent_ids = $parent_q->execute()->fetchCol();

foreach ($parent_ids as $parent_id) {
  $parent = $node_storage->load($parent_id);
  if ($parent->getType() !== 'dc_object') {
    print("parent " . $parent->id() . " is not a dc_object!\n");
    continue;
  }
  if (!empty($islandora_utils->getMediaWithTerm($parent, $thumbnail_term))) {
    print("parent " . $parent->id() . " already has a thumbnail!\n");
    continue;
  }

  $child_query = \Drupal::entityQuery('node')->condition('type', 'dc_object')->condition('field_member_of', $parent_id)->sort('field_weight', 'ASC')->range(0, 1);
  $query_result = $child_query->execute();
  $child = $node_storage->load(reset($query_result));
  print("parent " . $parent->id() . " -> child " . $child->id() . "\n");
  $thumbnail = $islandora_utils->getMediaWithTerm($child, $thumbnail_term);

  if (empty($thumbnail->field_media_image)) {
    print("\tChild has no thumbnail file!\n");
    continue;
  }

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
