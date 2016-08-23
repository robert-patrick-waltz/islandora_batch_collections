<?php

/**
 * @file
 * Drush integration file for the Islandora Ingest Collections program.
 */

/**
 * Implements hook_drush_help().
 */
function islandora_ingest_collections_drush_help($command) {
  switch ($command) {
    case 'drush:create_islandora_collections_from_dsv':
      return dt('Ingests Islandora collections using the specified data in a DSV file.');
  }
}

/**
 * Implements hook_drush_command().
 */
function islandora_ingest_collections_drush_command() {
  $items = array();
  $items['create-islandora-collections-from-dsv'] = array(
    'description' => dt('Creates Islandora collections using the specified data in a DSV File.'),
    'options' => array(
      'input' => array(
        'description' => 'The absolute path to the delimiter-separated file. ' ,
        'required' => 'TRUE',
      ),
      'create_node' => array(
        'description' => 'Create a Drupal node for each collection ' .
        'Defaults to TRUE.',
      ),
    ),
    'examples' => array(
      'Standard example' => 'drush --user=admin create-islandora-collections-from-dsv   --input=/tmp/cdmcollectiondata/collection_data.dsv',
      'Alias example' => 'drush --user=admin cicfc  --input=/tmp/cdmcollectiondata/collection_data.tsv',
    ),
    'aliases' => array('cicfc'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
  );
  return $items;
}

/**
 * Callback function for drush create-islandora-collections-from-dsv.
 */
function drush_islandora_ingest_collections_create_islandora_collections_from_dsv() {
    try {
        $params = array(
            'input' => drush_get_option('input'),
            'create_node' => drush_get_option('create_node', FALSE),
        );

        if (!file_exists($params['input'])) {
            return drush_set_error(dt("Can't find data ;file at @path.", array('@path' => $params['input'])));
        }
        populateIslandoraCollectionFromDsv($params);
    } catch (Exception $e) {
      drupal_set_message(t('Error ingesting Islandora collections. %t',
        array('%t' => $e->getMessage()), 'error'));
      watchdog('Error ingesting Islandora collections. %t',
        array('%t' => $e->getMessage()), WATCHDOG_ERROR);
    }
}
