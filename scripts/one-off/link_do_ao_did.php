<?php

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

$database = \Drupal::database();
$query = $database->select('node__field_digital_id', 'did');
$query->fields('did', ['entity_id']);
$query->fields('fri', ['entity_id']);
$query->join('node__field_resource_identifier', 'fri', 'did.field_digital_id_value = fri.field_resource_identifier_value');
$query->leftJoin('node__field_source', 'fs', 'did.entity_id = fs.entity_id');
$query->isNull('fs.entity_id');
#$query->range(0,50);
$results = $query->execute();

$node_manager = \Drupal::entityTypeManager()->getStorage('node');
foreach ($results->fetchAllKeyed() as $doid => $aoid) {
    $do = $node_manager->load($doid);
    #$ao = $node_manager->load($aoid);
    #print('linking '.$do->label(). ' to ' . $ao->label() . "\n");
    print("linking $doid to $aoid\n");
    $do->set('field_source', $aoid);
    $do->save();
}
$accountSwitcher->switchBack();
