<?php


namespace utkdigitalinitiatives\islandora\ingest\collections;

/**
 * Keep a cache of  fedora objects labels for ease of
 * reuse through iterations of series.
 *
 * Not Thread Safe!
 *
 * @author rwaltz
 */
class IslandoraFedoraObjectLabelCache
{

    // hash containing all the object model fedora objects that have been
    // downloaded. No need to download more than once, they are not going to
    // change.
    private $fedoraObjectCache = array();
    private $connection;

    public function __construct($tuque_connection) {
        $this->connection = $tuque_connection;
    }

    public function getObjectLabel($pid) {
        if (!isset($this->fedoraObjectCache[$pid]) && (substr_count($pid, ":") > 0)) {
            $content_model_object = $this->connection->repository->api->a->getObjectProfile($pid);

             if (!$content_model_object) {
                 throw Exception("$pid is not found. Can not proceed to add collection!");
             }

            $this->fedoraObjectCache[$pid] = $content_model_object['objLabel'];
        } elseif ($pid === 'root') {
            $this->fedoraObjectCache[$pid] = 'root';
        }
        return $this->fedoraObjectCache[$pid];
    }

}