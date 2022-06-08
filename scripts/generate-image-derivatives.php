<?php

use Drupal\system\Entity\Action;
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

$original_tid = reset(
    \Drupal::entityQuery('taxonomy_term')
      ->condition('field_external_uri', 'http://pcdm.org/use#OriginalFile')
      ->execute()
  );

$intermediate_tid = reset(
    \Drupal::entityQuery('taxonomy_term')
      ->condition('field_external_uri', 'http://pcdm.org/use#IntermediateFile')
      ->execute()
  );
$service_tid = reset(
    \Drupal::entityQuery('taxonomy_term')
      ->condition('field_external_uri', 'http://pcdm.org/use#ServiceFile')
      ->execute()
  );

$originals_query = \Drupal::database()->select('media', 'm');
$originals_query->join('media__field_media_use', 'u', 'm.mid = u.entity_id');
$originals_query->join('media__field_media_of', 'o', 'm.mid = o.entity_id');
$originals_query->join('media__field_mime_type', 'mime', 'm.mid = mime.entity_id');
$originals_query->fields('o', ['field_media_of_target_id'])
  ->condition('u.field_media_use_target_id', $original_tid)
  ->condition('mime.field_mime_type_value', 'image/%%', 'LIKE');
$nodes_w_originals = $originals_query->execute()->fetchCol();

# We are likely to get more service files than originals because we aren't limiting
# to images. This is because we sometimes use PDF (or perhaps other) service files.
$service_query = \Drupal::database()->select('media', 'm');
$service_query->join('media__field_media_use', 'u', 'm.mid = u.entity_id');
$service_query->join('media__field_media_of', 'o', 'm.mid = o.entity_id');
$service_query->fields('o', ['field_media_of_target_id'])
  ->condition('u.field_media_use_target_id', $service_tid);
$nodes_w_service = $service_query->execute()->fetchCol();

# Similarly, we may have intermediates that aren't images.
$intermediate_query = \Drupal::database()->select('media', 'm');
$intermediate_query->join('media__field_media_use', 'u', 'm.mid = u.entity_id');
$intermediate_query->join('media__field_media_of', 'o', 'm.mid = o.entity_id');
$intermediate_query->fields('o', ['field_media_of_target_id'])
  ->condition('u.field_media_use_target_id', $intermediate_tid);
$nodes_w_intermediates = $intermediate_query->execute()->fetchCol();

$results = array_diff($nodes_w_originals, $nodes_w_service);

$original_action = Action::load('image_generate_a_service_file_from_an_original_file');
$intermediate_action = Action::load('image_generate_a_service_file_from_an_intermediate_file');
$thumb_action = Action::load('image_generate_a_thumbnail_from_a_service_file');

foreach ($results as $nid) {
  $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
  $action = (in_array($nid, $nodes_w_intermediates)) ? $intermediate_action : $original_action;
  printf("Performing '%s' on '%s' at %s\n", $action->id(), $node->toUrl()->toString(), date(DATE_ATOM));
  $action->execute([$node]);
  #$thumb_action->execute([$node]);
}
printf("DONE ISSUING ACTIONS:\t%d\n", time());
$accountSwitcher->switchBack();

