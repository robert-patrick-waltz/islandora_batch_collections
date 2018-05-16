<?php

/**
    Islandora_Ingest_Collections Module
    Copyright (C) 2016  Robert Patrick Waltz

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

namespace utkdigitalinitiatives\islandora\ingest\collections;
/**
 * Description of IslandoraSimpleDSVCollectionPopulator
 *
 * @author rwaltz
 */
class IslandoraSimpleDSVCollectionPopulator 
{
    private $doIslandoraIngestDsvNode = FALSE;
    private $tuque;
    public function __construct($islandora_tuque, $islandoraIngestNode = FALSE  ) {
        $this->doIslandoraIngestDsvNode = $islandoraIngestNode;
        $this->tuque = $islandora_tuque;
    }
    /**
     * Ingest an Islandora collection object.
     *
     * @param array $collection_data
     *   The configuration data for one CONTENTdm collection.
     */
    public function islandoraIngestCollection($dsvCollection)
    {
        try {
            $repository = $this->tuque->repository;
            $collection_object = $repository->constructObject($dsvCollection->getPid());
            $collection_object->label = $dsvCollection->getLabel();

            // Add the COLLECTION_POLICY ds.
            $coll_policy_datastream = $collection_object->constructDatastream('COLLECTION_POLICY', 'M');
            $coll_policy_datastream->label = 'Collection policy';
            $coll_policy_datastream->mimetype = 'text/xml';
            $coll_policy_datastream->setContentFromString($dsvCollection->getCollectionPolicyXml());
            $collection_object->ingestDatastream($coll_policy_datastream);

            // Add the MODS ds.
            $mods_datastream = $collection_object->constructDatastream('MODS', 'M');
            $mods_datastream->label = 'MODS Record';
            $mods_datastream->mimetype = 'application/xml';
            $mods_datastream->setContentFromString($dsvCollection->getModsXml());
            $collection_object->ingestDatastream($mods_datastream);


            // Add relationships.
            $rels = $collection_object->relationships;
            
            // The root of the repository will not have a parent pid, but rather
            // just the namespace of the repository
            $parent = $dsvCollection->getParent();

            if (isset($parent) && substr_count($parent, ":") > 0) {
                $rels->add('info:fedora/fedora-system:def/relations-external#', 'isMemberOfCollection', $dsvCollection->getParent(), FALSE);
            }
            $rels->add('info:fedora/fedora-system:def/model#', 'hasModel', 'islandora:collectionCModel', FALSE);

            $repository->ingestObject($collection_object);
            \drupal_set_message(t('Ingested Islandora collection object %t (PID %p).',
              array('%t' => $dsvCollection->getLabel(), '%p' => $dsvCollection->getPid())));
            \watchdog('islandora_migrate_cdm_collections', 'Ingested Islandora collection object %t (PID %p).',
              array('%t' => $dsvCollection->getLabel(), '%p' => $dsvCollection->getPid()), WATCHDOG_INFO);
        } catch (Exception $e) {
          \drupal_set_message(t('Error ingesting Islandora collection object %t (PID %p).',
            array('%t' => $dsvCollection->getLabel(), '%p' => $dsvCollection->getPid())), 'error');
          \watchdog('islandora_migrate_cdm_collections', 'Error ingesting Islandora collection object %t (PID %p).',
            array('%t' => $dsvCollection->getLabel(), '%p' => $dsvCollection->getPid()), WATCHDOG_ERROR);
        }

    }


    /**
     * The thumbnail_filepath may not be set, or if set, it may be incorrectly
     * formated. If thumbnail_filepath cannot be resolved, then return
     * the Islandora folder image
     * 
     * @param $thumbnail_filepath
     *   Filepath to the thumbnail
     * @return $thumbnail_filepath 
     *   Filepath to the thumbnail
     */
    private function getCompleteThumbnailPath($thumbnail_filepath) {
        if ((strlen($thumbnail_filepath) == 0) || !file_exists($thumbnail_filepath) || !is_file($thumbnail_filepath)) {
            $thumbnail_filepath = \drupal_get_path('module', 'islandora_basic_collection') .
              '/images/folder.png';
        }
        return $thumbnail_filepath;
    }
    
    /**
     * Do some validation checks on the content type.
     * 
     */
 
    private function validateDrupalContentType($content_type) 
    {
        // Replace all non letters, numbers, and spaces with _ prior to node_type_load(),
        // same as Drupal core does, in case the user copies the machine name from the URL,
        // which uses - instead of _.  
        $content_type = preg_replace('/[^a-zA-Z0-9]+/', '_', $content_type);
        if ($content_type) {
            if (!$type = \node_type_load($content_type)) {
                throw Exception("Can't find the content type $content_type.");
            }

            if (!$type->has_title) {
              throw Exception("Content type $content_type has no title field.");
            }

            $required_fields = array(
              'field_description',
              'field_cdm_alias',
              'field_thumbnail',
              'field_pid',
            );
            $fields = \field_info_instances('node', $content_type);
            foreach ($required_fields as $required_field) {
              if (!array_key_exists($required_field, $fields)) {
                  throw Exception("Content type $content_type has no $required_field field.");
              }
            }
        }
    
    }
}
