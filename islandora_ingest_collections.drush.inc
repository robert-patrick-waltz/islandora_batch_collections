<?php

/**
 * @file
 * Drush integration file for the Islandora Ingest Collections program.
 */
define('DELIMITER',',');
define('ENCLOSURE','"');
define('ESCAPE','"');
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
        return drush_set_error(dt("Can't find data file at @path.", array('@path' => $params['input'])));
      }
      $islandoraSimpleDSVCollectionPopulator = new edu\utk\islandora\ingest\collections\IslandoraSimpleDSVCollectionPopulator($params['create_node']);
      $collections_data = retrieve_data_from_dsv($params['input']);
      foreach ($collections_data as $data) {
          $simpleDSVCollection =  new edu\utk\islandora\ingest\collections\SimpleDSVCollection($data);
            try {
                $islandoraSimpleDSVCollectionPopulator->islandoraIngestCollection($simpleDSVCollection);
            } catch (Exception $e) {
                drupal_set_message(t('Error ingesting Islandora collection object %t (PID %p).',
                    array('%t' => $simpleDSVCollection->getLabel(), 
                          '%p' => $simpleDSVCollection->getPid()), 'error'));
                watchdog('create_islandora_collections_from_dsv', 'Error ingesting Islandora collection object %t (PID %p).',
                    array('%t' => $simpleDSVCollection->getLabel(), 
                         '%p' => $simpleDSVCollection->getPid()), WATCHDOG_ERROR);
            }
      }
    } catch (Exception $e) {
      drupal_set_message(t('Error ingesting Islandora collections. %t',
        array('%t' => $e->getMessage()), 'error'));
      watchdog('Error ingesting Islandora collections. %t',
        array('%t' => $e->getMessage()), WATCHDOG_ERROR);
    }
}

/**
 * Retrieve data from a delimiter-separated values file
 * The defaults assume a comma separated values file
 *
 * The DSV file must have the following columns:
 *   -title
 *   -cdm_alias (Text/Text field)
 *   -description (Long text/Textarea (multiple rows))
 *   -thumbnail (Image/Image)
 *   -object_id (Text/Text field)
 *
 * @param string $data_file_path
 *   The absolute filesystem path to the comma-separated-value file 
 * @param string $delimiter_char
 *   The optional delimiter parameter sets the field delimiter (one character only)
 *   Default is the ',' char, separates one data cell from another
 * @param string $enclosure_char
 *   The optional enclosure parameter sets the field enclosure character (one character only).
 *   The Default is '"' char, encapsulates the data cell data
 * @param string $escape_char
 *   The optional escape parameter sets the escape character (one character only). 
 *   The Default is '"', provides use of enclosure char with in data cell data
 * 
 */
function retrieve_data_from_dsv($data_file_path, $delimiter_char = DELIMITER, 
            $enclosure_char = ENCLOSURE, $escape_char = ESCAPE) {
    $dsv_data = array();
    if (($data_file_handle = fopen($data_file_path, "r")) !== FALSE) {
        while (($dsv_row = fgetcsv($data_file_handle, 0, $delimiter_char,
                $enclosure_char,$escape_char)) !== FALSE) {
            $dsv_data[] = $dsv_row;
        }
    } else {
        throw new Exception("Unable to open $data_file_path");
    }
    return $dsv_data;
}
