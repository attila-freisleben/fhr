<?php

$distance = $_vars['distance'][0];
$city = $_vars['address.city'][0];
$nat = $_vars['address.country'][0];


$q = "select  name,latitude,longitude from GeoNames.geonames where country_code='$nat' and feature_class='P' and name='$city'";
$db->db_exec($q);
$rs = $db->db_fetch();
$lat = $rs['LATITUDE'];
$lon = $rs['LONGITUDE'];

$poptotal = 0;
$q = "SELECT * FROM fhiringRange.geonames WHERE feature_class='P' and acos(sin(radians($lat)) * sin(radians(latitude)) + cos(radians($lat)) * cos(radians(latitude)) * cos(radians(longitude) - (radians($lon)))) * 6371 <= $distance";
$db->db_exec($q);
for (; $rs = $db->db_fetch();) {
    if ($rs['POPULATION'] == 0)
        $rs['POPULATION'] = 10;
    $poptotal += $rs['POPULATION'];
    $res[] = array('Name' => $rs['NAME'], 'lat' => $rs['LATITUDE'], 'lon' => $rs['LONGITUDE'], 'Population' => $rs['POPULATION']);
}
$res['total'] = $poptotal;


?>