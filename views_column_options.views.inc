<?php
/**
 * @file
 * Includes views_column_selector.views.inc.
 */

/**
 * Implements hook_views_data_alter().
 */
function views_column_options_views_data_alter(&$data) {
  $data['views']['views_column_options'] = [
    'title' => t('Field Options'),
    'filter' => [
      'title' => t('Field Options'),
      'help' => 'Field Options',
      'id' => 'views_column_options',
    ],
  ];
}
