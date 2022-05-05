<?php

use Drupal\Core\Session\UserSession;

$username = $extra[0];
if (empty($username)) {print("Please enter a username with the fedoraadmin role as a script parameter! E.g. `drush scr <script> -- <username>`.\n"); return false;}
$account = user_load_by_name($username);
if (!$account || !in_array('fedoraadmin',$account->getRoles())) { print("Either $username doesn't exist or doesn't have the fedoradmin role!\n"); return false; }
$accountSwitcher = \Drupal::service('account_switcher');
$userSession = new UserSession([
  'uid'   => $account->id(),
  'name'  => $account->getAccountName(),
  'roles' => $account->getRoles(),
]);
$accountSwitcher->switchTo($userSession);
