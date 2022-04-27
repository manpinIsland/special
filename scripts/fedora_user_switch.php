<?php
/**
 * fedora user switch
 *
 * This script is intended to be required by other scripts where their actions
 * could cause derivative or indexing actions to ensure an appropriate
 * user account is executing the actions.
 *
 * E.g. at the beginning of the script add `require fedora_user_switch.php;`.
 */

use Drupal\Core\Session\UserSession;

$username = $extra[0];
if (empty($username)) {print("Please enter a username with the fedoraadmin role as a script parameter! E.g. `drush scr <script.php> -- username`.\nExiting.\n"); exit;}
$account = user_load_by_name($username);
if (!$account || !in_array('fedoraadmin',$account->getRoles())) { print("Either $username doesn't exist or doesn't have the fedoradmin role! Exiting.\n"); exit; }
$accountSwitcher = \Drupal::service('account_switcher');
$userSession = new UserSession([
  'uid'   => $account->id(),
  'name'  => $account->getAccountName(),
  'roles' => $account->getRoles(),
]);
$accountSwitcher->switchTo($userSession);
