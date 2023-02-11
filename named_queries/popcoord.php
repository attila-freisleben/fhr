<?php
$nat = $_vars['address.country'][0];
$q = "select  round(latitude,2) as lat,round(longitude,2) as lon,sum(population) as pop from GeoNames.geonames where country_code='$nat' and feature_class='P' group by 1,2 order by 1,2";
$db->db_exec($q);

for (; $rs = $db->db_fetch();) {
    $res[] = $rs;
}


