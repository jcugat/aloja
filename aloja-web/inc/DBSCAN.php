<?php

namespace alojaweb\inc;

class DBSCAN
{
    /**
     * 
     * @param type $data
     * @param type $e
     * @param type $minimumPoints
     */
    static function ll_dbscan($data, $e, $minimumPoints = 10) {
        $clusters = array();
        $visited = array();

        foreach ($data as $index => $datum) {
            echo "\n\n\n\n\n";
            echo "dins foreach $index\n";
            echo "visited:\n";
            print_r($visited);
            echo "\n";
            if (in_array($index, $visited))
                continue;

            $visited[] = $index;

            // $regionPoints = self::_ll_points_in_region(array($index => $datum), $data, $e);
            $regionPoints = self::_ll_points_in_region($datum, $data, $e);
            echo "region minimumPoints: ".$minimumPoints." points: ".count($regionPoints)."\n";
            echo "\n";
            if (count($regionPoints) >= $minimumPoints) {
                // $clusters[] = self::_ll_expand_cluster(array($index => $datum), $regionPoints, $e, $minimumPoints, $visited);
                $clusters[] = self::_ll_expand_cluster($datum, $regionPoints, $e, $minimumPoints, $visited);
            }
        }

        return $clusters;
    }

    /**
     * 
     * @param type $point
     * @param type $data
     * @param type $epsilon
     * @return type
     */
    static function _ll_points_in_region($point, $data, $epsilon) {
        $region = array();
        foreach ($data as $index => $datum) {

            echo "region checking point ".$datum[0]." ".$datum[1]."\n";

            if (self::linear_euclidian_distance($point, $datum) < $epsilon) {
                $region[$index] = $datum;
            }
        }
        echo "region result\n";
        print_r($region);
        echo "\n";
        return $region;
    }

    static function linear_euclidian_distance($a, $b) {
        echo "distance count: ".count($a)." ".count($b);

        if (count($a) != count($b)) {
            echo "\n";
            return false;
        }

        $distance = 0;
        for ($i = 0; $i < count($a); $i++) {
            $distance += pow($a[$i] - $b[$i], 2);
        }

        echo " result: ".sqrt($distance)."\n";
        return sqrt($distance);
    }

    /**
     * 
     * @param type $point
     * @param type $data
     * @param type $epsilon
     * @param type $minimumPoints
     * @param type $visited
     */
    static function _ll_expand_cluster($point, $data, $epsilon, $minimumPoints, &$visited) {
        $cluster[] = $point;

        foreach ($data as $index => $datum) {
            if (!in_array($index, $visited)) {
                $visited[] = $index;
                // $regionPoints = self::_ll_points_in_region(array($index => $datum), $data, $epsilon);
                $regionPoints = self::_ll_points_in_region($datum, $data, $epsilon);

                if (count($regionPoints) > $minimumPoints) {
                    $cluster = self::_ll_join_clusters($regionPoints, $cluster);
                }
            }

            // supposed to check if it belongs to any clusters here.
            // only add the point if it isn't clustered yet.
            // $cluster[] = array($index => $datum);
            $cluster[$index] = $datum;
        }
        echo "expand cluster:\n";
        print_r($cluster);
    }
    
    /**
     * 
     * @param type $one
     * @param type $two
     * @return type
     */
    static function _ll_join_clusters($one, $two) {
        // return array_merge($one, $two);
        return $one + $two;
    }
}
