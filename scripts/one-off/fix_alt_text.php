<?php
use Drupal\Core\Session\UserSession;
use Drupal\user\Entity\User;

$userid = 3;
$account = User::load($userid);
$accountSwitcher = \Drupal::service('account_switcher');
$userSession = new UserSession([
  'uid'   => $account->id(),
  'name'  => $account->getUsername(),
  'roles' => $account->getRoles(),
]);
$accountSwitcher->switchTo($userSession);

$media_query = \Drupal::database()->select('media', 'm');
$media_query->join('media__field_media_image', 'i', 'm.mid = i.entity_id');
$media_query->join('media__field_media_of', 'o', 'm.mid = o.entity_id');
$media_query->fields('m', ['mid'])
  ->condition('m.bundle', 'image')
  ->isNotNull('o.field_media_of_target_id')
  ->isNull('i.field_media_image_alt');

$mids = $media_query->execute()->fetchCol();
$media_storage = \Drupal::entityTypeManager()->getStorage('media');
foreach ($mids as $mid) {
  print("Processing media $mid.");
  $media = $media_storage->load($mid);
  if (is_null($media->field_media_of->entity)) { print(" No node to reference!\n"); continue; }
  $alt = $media->field_media_of->entity->label();
  print(" Using node title '$alt'\n");
  $images = $media->get('field_media_image');
  foreach ($images as $delta => $image) {
    if (empty($image->alt)) {
      $image->set('alt', $alt);
    }
    if (empty($image->title)) {
      $image->set('title', $alt);
    }
  }
  $media->save();
}
