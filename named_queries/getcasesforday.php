<?php

$distance = isset($_vars['distance'][0]) ? $_vars['distance'][0] : 10;
$city = isset($_vars['address.city'][0]) ? $_vars['address.city'][0] : "";
$nat = $_vars['address.country'][0];
$doy = $_vars['doy'][0];


$q = "";

$db->db_exec($q);
for (; $rs = $db->db_fetch();) {
    $res[] = array('szakma' => $rs['SZAKMA'], 'avg_cases_per_10t_daily' => $rs['AVGCPTT'], 'max_cases_per_10t_daily' => $rs['MAXCPTT'], 'avg_daily_patient_doc' => $avg_daily_patient[$rs['SZAKMA']]);
}
