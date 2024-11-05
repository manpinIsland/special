<?php

/**
 * @file
 */

/**
 * Implements hook_views_data().
 */
function islandora_local_views_data() {
  $data['views']['table']['group'] = t('Custom Global');
  $data['views']['table']['join'] = [
    // #global is a special flag which allows a table to appear all the time.
    '#global' => [],
  ];
  $data['views']['local_breadcrumbs_views_field'] = [
    'title' => t('Breadcrumbs'),
    'help' => t('Prints breadcrumbs as a field'),
    'field' => [
      'id' => 'local_breadcrumbs_views_field',
    ],
  ];
  return $data;
}
