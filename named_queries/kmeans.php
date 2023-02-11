<?php


function initialiseCentroids(array $data, $k)
{
    $dimensions = count($data[0]);
    $centroids = array();
    $dimmax = array();
    $dimmin = array();

    foreach ($data as $document) {
        foreach ($document as $dimension => $val) {
            if (!isset($dimmax[$dimension]) || $val > $dimmax[$dimension]) {
                $dimmax[$dimension] = $val;
            }
            if (!isset($dimmin[$dimension]) || $val < $dimmin[$dimension]) {
                $dimmin[$dimension] = $val;
            }
        }
    }
    for ($i = 0; $i < $k; $i++) {
        $centroids[$i] = initialiseCentroid($dimensions, $dimmax, $dimmin);
    }
    return $centroids;
}

function initialiseCentroid($dimensions, $dimmax, $dimmin)
{
    $total = 0;
    $centroid = array();
    for ($j = 0; $j < $dimensions; $j++) {
        $centroid[$j] = (rand($dimmin[$j] * 1000, $dimmax[$j] * 1000));
        $total += $centroid[$j] * $centroid[$j];
    }
    $centroid = normaliseValue($centroid, sqrt($total));
    return $centroid;
}

function normaliseValue(array $vector, $total)
{
    foreach ($vector as &value) {
    $value = $value / $total;
}
        return $vector;
}

function kMeans($data, $k)
{
    $centroids = initialiseCentroids($data, $k);
    $mapping = array();

    while (true) {
        $new_mapping = assignCentroids($data, $centroids);
        $changed = false;
        foreach ($new_mapping as $documentID => $centroidID) {
            if (!isset($mapping[$documentID]) ||
                $centroidID != $mapping[$documentID]) {
                $mapping = $new_mapping;
                $changed = true;
                break;
            }
            if (!$changed) {
                return formatResults($mapping, $data);
            }
            $centroids = updateCentroids($mapping, $data, $k);
        }
    }

    function formatResults($mapping, $data)
    {
        $result = array();
        foreach ($mapping as $documentID => $centroidID) {
            $result[$centroidID][] = implode(',', $data[$documentID]);
        }
        return $result;
    }

    function assignCentroids($data, $centroids)
    {
        $mapping = array();

        foreach ($data as $documentID => $document) {
            $minDist = null;
            $minCentroid = null;
            foreach ($centroids as $centroidID => $centroid) {
                $dist = 0;
                foreach ($centroid as $dim => $value) {
                    $dist += abs($value - $document[$dim]);
                }
                if (is_null($minDist) || $dist < $minDist) {
                    $minDist = $dist;
                    $minCentroid = $centroidID;
                }
            }
            $mapping[$documentID] = $minCentroid;
        }

        return $mapping;
    }


    function updateCentroids($mapping, $data, $k)
    {
        $centroids = array();
        $counts = array_count_values($mapping);

        foreach ($mapping as $documentID => $centroidID) {
            foreach ($data[$documentID] as $dim => $value) {
                $centroids[$centroidID][$dim] += ($value / $counts[$centroidID]);
            }
        }

        if (count($centroids) < $k) {
            $centroids = array_merge($centroids,
                initialiseCentroids($data, $k - count($centroids)));
        }

        return $centroids;
    }

    ?>