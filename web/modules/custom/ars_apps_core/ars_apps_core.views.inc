<?php

/**
 * @file
 * Views hooks for ARS Apps Core module.
 */

/**
 * Implements hook_views_data().
 */
function ars_apps_core_views_data(): array {
  $data = [];

  $data['views']['exposed_filter_tags'] = [
    'title' => t('Exposed filter tags'),
    'help' => t('Displays active exposed filters as USWDS tag components.'),
    'area' => [
      'id' => 'exposed_filter_tags',
    ],
  ];

  return $data;
}
