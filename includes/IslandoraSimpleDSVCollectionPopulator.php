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
    public function islandoraIngestCollection(SimpleDSVCollection $dsvCollection) 
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

            // Add THUMBNAIL ds. If we don't have thumbnails in the input data,
            // use the image provided by the collection solution pack.
            $thumbnail_filepath = $this->getCompleteThumbnailPath($dsvCollection->getThumbnailFilepath());
            $tn_datastream = $collection_object->constructDatastream('TN', 'M');

            // Dectect the mime type of the thumbnail
            $tn_mime_detector = new \MimeDetect();
            $tn_datastream->mimetype = $tn_mime_detector->getMimetype($thumbnail_filepath);
            $tn_datastream->label = 'Thumbnail';
            $tn_datastream->setContentFromFile($thumbnail_filepath);
            $collection_object->ingestDatastream($tn_datastream);

            // Add relationships.
            $rels = $collection_object->relationships;
            
            // The root of the repository will not have a parent pid, but rather
            // just the namespace of the repository
            $parent = $dsvCollection->getParent();

            if (substr_count($parent, ":") > 0) {
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
            array('%t' => $dsvCollection->getLabel(), '%p' => $dsvCollection->getPid()), 'error'));
          \watchdog('islandora_migrate_cdm_collections', 'Error ingesting Islandora collection object %t (PID %p).',
            array('%t' => $dsvCollection->getLabel(), '%p' => $dsvCollection->getPid()), WATCHDOG_ERROR);
        }
        if ($this->doIslandoraIngestDsvNode) {
            $this->islandoraIngestDsvNode($dsvCollection);
        }
    }

    /**
     * Ingest a Drupal node to correspond to an Islandora collection object.
     *
     * All content types must have the following fields:
     *   -title
     *   -cdm_alias (Text/Text field)
     *   -description (Long text/Textarea (multiple rows))
     *   -thumbnail (Image/Image)
     *   -object_id (Text/Text field)
     *
     * @param string $input_path
     *   The absolute filesystem path to the tab-separated-value file generated by
     *   get_collection_data.php.
     * @param array $collection_data
     *   The configuration data for one CONTENTdm collection.
     * @param string $content_type
     *   The Drupal content type to use for the node.
     * @param string $namespace
     *   The value of the --namespace Drush option.
     */
    private function islandoraIngestDsvNode(SimpleDSVCollection $dsvCollection) 
    {
       $thumbnail_filepath = $this->getCompleteThumbnailPath($dsvCollection->getThumbnailFilepath());
       $file = N;
        if (file_exists($thumbnail_filepath)) {
            unset($file);
            $file = \file_save_data(file_get_contents($thumbnail_filepath),
              \file_default_scheme() . '://' . basename($thumbnail_filepath));
            if ($file) {
                $file->status = FILE_STATUS_PERMANENT;
                $file->display = 1;
                $file->description = basename($thumbnail_filepath);
                $file->uid = 1;
            }
        }

        // Create the node object.
        $node = new \stdClass();
        $node->title = trim($dsvCollection->getLabel());
        $node->type = $dsvCollection->getDrupalContentType();
        $node->status = 1;
        $node->promote = 0;
        $node->sticky = 0;
        $node->language = LANGUAGE_NONE;
        $node->uid = 1;
        
        if (strlen($dsvCollection->getThumbnailFilepath()) > 0) {
          $node->field_thumbnail[LANGUAGE_NONE][0] = (array) $file;
        }

        $node->field_pid[LANGUAGE_NONE][0]['value'] = $dsvCollection->getPid() ;

        $node->field_cdm_alias[LANGUAGE_NONE][0]['value'] = $dsvCollection->getLabel();
        
        if (strlen($dsvCollection->getModsDescription() > 0)) {
          $node->field_description[LANGUAGE_NONE][0]['value'] = $dsvCollection->getModsDescription();
          $node->field_description[LANGUAGE_NONE][0]['format'] = 'plain_text';
        }

        // Save the node.
        if ($node->title) {
          $node = \node_submit($node);
          if ($node->validated) {
            \node_save($node);
          }
        }

        print "Collection node with title $node->title created\n";
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
