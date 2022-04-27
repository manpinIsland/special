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

$nid_count = \Drupal::entityQuery('node')->condition('type','dc_object')->exists('field_date_digitized')->count()->accessCheck(FALSE)->execute();
## Loop this and pass them into loadMultiple before handing it off the loaded nodes to the action.
$batch_size = 100;
$index_node_action = Action::load('index_node_in_fedora');

for ($index = 0; $index <= $nid_count; $index += $batch_size) {
    $query = \Drupal::entityQuery('node')->condition('type','dc_object')->exists('field_date_digitized')->range($index,$batch_size)->accessCheck(FALSE);
    $nids = $query->execute();
    print("Range $index: ".implode(" ", $nids)."\n");
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    $index_node_action->execute($nodes);
}

printf("DONE ISSUING ACTIONS:\t%d\n", time());
$accountSwitcher->switchBack();
