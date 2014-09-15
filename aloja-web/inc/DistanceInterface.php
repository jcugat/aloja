<?php

namespace alojaweb\inc;

/**
 * Distance should return a number proportional to how dissimilar
 * the two instances are(with any metric)
 */
interface DistanceInterface
{
    public function dist(&$A, &$B);
}
