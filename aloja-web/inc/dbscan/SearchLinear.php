<?php

namespace alojaweb\inc\dbscan;

use alojaweb\inc\dbscan\DistanceInterface;

class SearchLinear implements SearchInterface
{

    protected $data;
    protected $distanceClass;

    public function setData(&$data)
    {
        $this->data = $data;
    }

    public function setDistanceMode(DistanceInterface $distance)
    {
        $this->distanceClass = $distance;
    }

    /**
     * Search all the documents and return those that are within
     * $e distance of $doc
     *
     * @param  mixed $doc The document in whose region we are searching
     * @param  float $e   The maximum allowed distance from $doc
     * @return array The indexes of the documents that are the answer to the query
     */
    public function regionQuery($reference, $eps)
    {
        $neighborhood = array();
        foreach ($this->data as $point_id => $point) {

            // echo "distance from $reference to $point is ".$this->distanceClass->distance($point, $reference)."\n";

            if ($this->distanceClass->distance($point, $reference) < $eps) {
                $neighborhood[$point_id] = $point;
            }
        }
        return $neighborhood;
    }

}
