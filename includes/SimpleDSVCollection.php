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
 * SimpleDSVCollection assigns very basic properties to create a simple
 * Islandora Collection.  Extend the class if more properties are needed, or
 * if the Collection XML or MODS XML need alteration
 */
class SimpleDSVCollection {

    // parent fedora pid of the new collection object to be created
    private $parent;
    // namespace of the new object, concatentated with object_id to make a pid
    private $namespace;
    // object_id of the new object, concatentated with namespace to make a pid
    private $objectId;
    // concatentation of namespace and object_id to create a fedora pid
    private $pid;
    // the fedora label of the object
    private $label;
    // assigned to a Drupal Node's content type
    private $drupalContentType = "page";
    // assigned to the MODS field description
    private $modsDescription;
    // assigned to the MODS field type of resource
    private $modsTypeOfResource;
    // full path to the thumbnail used to represent the Collection
    private $thumbnailFilepath;
    // simple XML representing a blank collection policy
    protected $collectionPolicyXml;
    // simple MODS XML containing only the pid, label and description
    protected $modsXml;
    // namespace to be applied to objects created
    private $contentModelNamespace;
    private $objectLabelCache;
    private $contentModels = array();
    private $contentPolicyXMLTemplate = <<<EOCP
<?xml version="1.0" encoding="UTF-8"?>
<collection_policy xmlns="http://www.islandora.ca" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" name="" xsi:schemaLocation="http://www.islandora.ca https://raw.githubusercontent.com/Islandora/islandora_solution_pack_collection/7.x/xml/collection_policy.xsd">
<content_models/>
<search_terms/>
<staging_area/>
<relationship>isMemberOfCollection</relationship>
</collection_policy>
EOCP;

    public function __construct( $collection_data,  $islandoraFedoraObjectLabelCache) {
        if (isset($islandoraFedoraObjectLabelCache)) {
            $this->objectLabelCache = $islandoraFedoraObjectLabelCache;
        } else {
            throw new \Exception("IslandoraFedoraObjectLabelCache is not set");
        }
        $this->validate_dsv_data($collection_data);
        $this->parent = strtolower($collection_data[0]);
        $this->namespace = strtolower($collection_data[1]);
        $this->objectId = $collection_data[2];
        $this->pid = $this->namespace . ':' . $this->objectId;
        $this->label = trim($collection_data[3]);
        $this->modsDescription = trim($collection_data[4]);
        $this->modsTypeOfResource = trim($collection_data[5]);


        if (strlen(trim($collection_data[4])) > 0) {
            $content_model_list = explode(',', trim($collection_data[4]));
            for ($i = 0; $i < count($content_model_list); ++$i) {
                $content_model_list[$i] = trim($content_model_list[$i]);
            }
            $this->contentModels = $content_model_list;
        }

        // The namespace of each content model should be pid of the collection to create, substituting '.' for ':'
        $this->contentModelNamespace = $this->namespace . '.' . $this->objectId;
    }

    /**
     * return the parent fedora pid of the new collection object to be created
     * 
     * @return type
     */
    public function getParent() {
        return $this->parent;
    }

    /**
     * return the namespace of the new object, concatentate with object_id to make a pid
     * 
     * @return type
     */
    public function getNamespace() {
        return $this->namespace;
    }

    /**
     * return object_id of the new object, concatentate with namespace to make a pid
     * 
     * @return type
     */
    public function getObjectId() {
        return $this->objectId;
    }

    /**
     * return the concatentation of namespace and object_id to create a fedora pid
     * 
     * @return type
     */
    public function getPid() {
        return $this->pid;
    }

    /**
     * return the fedora label for the object
     * 
     * @return type
     */
    public function getLabel() {
        return $this->label;
    }

    /**
     * return the MODS field description
     * 
     * @return type
     */
    public function getModsDescription() {
        return $this->modsDescription;
    }

    /**
     * return  MODS field Type of Resource
     * 
     * @return type
     */
    public function getModsTypeOfResource() {
        return $this->modsTypeOfResource;
    }

    /**
     * return full path to the thumbnail used to represent the Collection
     * 
     * @return type
     */
    public function getThumbnailFilepath() {
        return $this->thumbnailFilepath;
    }

    /**
     * return simple XML representing a blank collection policy
     * 
     * @return type
     */
    public function getCollectionPolicyXml() {
        #    <content_model name="Thesis" dsid="" namespace="islandora" pid="ir:thesisCModel"/>
        # add content model elements for each one listed in the dsv file
        # the name will come from the label of the content model
        # the namespace is derived from the pid of the collection being processed
        # the pid is the object pid of the content model (as supplied in the dsv file)
        # dsid is empty
        if (!isset($this->collectionPolicyXml)) {
            $collection_policy_dom = new \DOMDocument();
            $collection_policy_dom->preserveWhiteSpace = false;
            $collection_policy_dom->formatOutput = true;
            $collection_policy_dom->loadXml($this->contentPolicyXMLTemplate);
            $root_element = $collection_policy_dom->documentElement;

            $root_element->setAttribute('name', $this->getLabel());

            $content_models_nodelist = $collection_policy_dom->getElementsByTagName('content_models');
            if ($content_models_nodelist->length != 1) {
                throw new \Exception("The static collection policy xml has more than one content_models element: " . $this->collectionPolicyXml);
            }
            $content_models_node = $content_models_nodelist->item(0);
            foreach ($this->getContentModels() as $content_model_pid) {
                $content_model_element = $collection_policy_dom->createElement("content_model");
                $content_model_label = $this->objectLabelCache->getObjectLabel($content_model_pid);
                $content_model_element->setAttribute('name', $content_model_label);
                $content_model_element->setAttribute('namespace', $this->getContentModelNamespace());
                $content_model_element->setAttribute('pid', $content_model_pid);
                $content_model_element->setAttribute('dsid', "");
                $content_models_node->appendChild($content_model_element);
            }
            $this->collectionPolicyXml = $collection_policy_dom->saveXML();
        }
        return $this->collectionPolicyXml;
    }

    /**
     * return simple MODS XML containing only the pid, label and description
     * 
     * @return type
     */
    public function getModsXml() {
        // instead of using a template like above, it is easier fillling out
        // the variables in line
        if (!isset($this->modsXml)) {
            $this->modsXml = <<<EOMODS
<?xml version="1.0" encoding="UTF-8"?>
<mods xmlns="http://www.loc.gov/mods/v3" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xlink="http://www.w3.org/1999/xlink">
  <titleInfo>
    <title>$this->label</title>
  </titleInfo>
  <typeOfResource collection="yes"/>
  <genre authority="lctgm"/>
  <language>
    <languageTerm authority="iso639-2b" type="code">eng</languageTerm>
  </language>
  <identifier type="local">$this->pid</identifier>
</mods>
EOMODS;
        }
        return $this->modsXml;
    }

    // return the Drupal Node's content type, defaults to page
    function getDrupalContentType() {
        return $this->drupalContentType;
    }

    function getContentModelNamespace() {
        return $this->contentModelNamespace;
    }

    function getContentModels() {
        return $this->contentModels;
    }

    function validate_dsv_data($dsv_data) {
        if (count($dsv_data) != 5) {
            throw new \Exception("There must be a minimum of 5 columns in the Delimiter Separated Value file");
        }
        if (strlen($dsv_data[0]) == 0) {
            throw new \Exception("The parent object identifier column must contain a value");
        }
        if (strlen($this->objectLabelCache->getObjectLabel($dsv_data[0])) == 0) {
            throw new \Exception("The specified parent object $dsv_data[0] is not found or is not accessible.");
        }
        if (strlen($dsv_data[1]) == 0) {
            throw new \Exception("The namespace column must contain a value");
        }
        if (strlen($dsv_data[2]) == 0) {
            throw new \Exception("The object identifier column must contain a value");
        }
        if (strlen($dsv_data[3]) == 0) {
            throw new \Exception("The label column must contain a value");
        }
        if (strlen($dsv_data[4]) == 0) {
            throw new \Exception("The MODS description column must contain a value");
        }
    }

}
