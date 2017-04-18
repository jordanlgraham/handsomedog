<?php

/**
 * Implements manifest_views_data().
 */
function manifest_views_data() {
  $data = array();

  $data['manifest_user']['table']['group'] = t('Manifest user');
  
  $data['manifest_user']['table']['base'] = array(
   'field' => 'uid',
   'title' => t('Manifest user'),
   'help'  => t('Manifest user list'),
  );

  $data['manifest_user']['uid'] = array(
    'title' => t('User'),
    'help' => t('User'),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_numeric',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'relationship' => array(
      'base' => 'users',
      'base field' => 'uid',
      'handler' => 'views_handler_relationship',
    ),
  );

  $data['manifest_user']['manifest'] = array(
    'title' => t('Manifest'),
    'help' => t('Manifest'),
    'field' => array(
      'handler' => 'views_handler_field',
      'click sortable' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_string',
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_string',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
  );

  $data['manifest_user']['created'] = array(
    'title' => t('Created'),
    'help' => t('Created'),
    'field' => array(
      'handler' => 'views_handler_field_date',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_date',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
  );

  return $data;
}

/**
 * Implements manifest_views_data_alter().
 */
function manifest_views_data_alter(&$data) {

  $data['users']['manifest'] = array(
    'title' => t('Manifest'),
    'help' => t('Manifest user list'),
    'relationship' => array(
      'base' => 'manifest_user',
      'base field' => 'uid',
      'relationship field' => 'uid',
      'handler' => 'views_handler_relationship',
    ),
  );

}
