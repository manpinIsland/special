<?php

/**
 * Run from the commandline using drush: `drush -l <site url> scr generate-image-thumbnails`
 */

use Drupal\Core\Session\UserSession;
use Drupal\user\Entity\User;
printf("STARTING:\t%d\n", time());
$userid = 3;
$account = User::load($userid);
$accountSwitcher = \Drupal::service('account_switcher');
$userSession = new UserSession([
  'uid'   => $account->id(),
  'name'  => $account->getAccountName(),
  'roles' => $account->getRoles(),
]);
$accountSwitcher->switchTo($userSession);

$service_tid = reset(
    \Drupal::entityQuery('taxonomy_term')
      ->condition('field_external_uri', 'http://pcdm.org/use#ServiceFile')
      ->execute()
  );

// Tried using entityQuery for media to get the field_media_of value,
// but that took a long time and resorted in out of memory errors.
// The select query method is a *much* more efficient.

$service_query = \Drupal::database()->select('media', 'm');
$service_query->join('media__field_media_use', 'u', 'm.mid = u.entity_id');
$service_query->join('media__field_media_of', 'o', 'm.mid = o.entity_id');
$service_query->join('media__field_mime_type', 'mime', 'm.mid = mime.entity_id');
$service_query->fields('o', ['field_media_of_target_id'])
  ->condition('u.field_media_use_target_id', $service_tid);

$mime_group = $service_query->orConditionGroup()
  ->condition('mime.field_mime_type_value', 'image/%%', 'LIKE')
  ->condition('mime.field_mime_type_value', 'application/pdf', 'LIKE');
$service_query->condition($mime_group);

$nodes_w_service = $service_query->execute()->fetchCol();

$thumb_tid = reset(
    \Drupal::entityQuery('taxonomy_term')
      ->condition('field_external_uri', 'http://pcdm.org/use#ThumbnailImage')
      ->execute()
  );

$thumb_query = \Drupal::database()->select('media', 'm');
$thumb_query->join('media__field_media_use', 'u', 'm.mid = u.entity_id');
$thumb_query->join('media__field_media_of', 'o', 'm.mid = o.entity_id');
$thumb_query->fields('o', ['field_media_of_target_id'])
  ->condition('u.field_media_use_target_id', $thumb_tid);
$nodes_w_thumb = $thumb_query->execute()->fetchCol();

printf("COMPARE LISTS:\t%d\n", time());
#print("Nodes with service:\n");
#print_r($nodes_w_service);
#print("Nodes with thumb:\n");
#print_r($nodes_w_thumb);
$results = array_diff($nodes_w_service, $nodes_w_thumb);
printf("Nodes to process:\t%d", count($results));
#print_r($results);
printf("BUILDING ACTIONS:\t%d\n", time());
$action = \Drupal\system\Entity\Action::load('image_generate_a_thumbnail_from_an_original_file');

# Thumbnails run ~ 13.8k/hr on 4 consumers, which is quicker than the service derivatives, so
# so we are pegging its slower 3.5k every 20 minutes.
# Johnny - 2022-08-23 - COMMENTED OUT to do all the results
#$limit = 3500;
#if($limit < count($results)) {
  #printf("Only running %s on %d of %d possible.\n", $action->id(), $limit, count($results));
#}
#$to_run = array_slice($results, 0, $limit);

printf("STARTING ACTIONS:\t%d\n", time());
#print("Nodes after slice:\n");
#print_r($to_run);
#foreach ($to_run as $nid) {
foreach ($results as $nid) {
  $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
  #printf("Performing '%s' on '%s' at %s\n", $action->id(), $node->toUrl()->toString(), date(DATE_ATOM));
  if ($node) {
    printf("Performing '%s' on '/node/%d/media' at %s\n", $action->id(), $node->id(), date(DATE_ATOM));
    $action->execute([$node]);
  } else {
    printf("Could not find node ID %d\n", $nid);
  }
}
printf("DONE ISSUING ACTIONS:\t%d\n", time());
$accountSwitcher->switchBack();
