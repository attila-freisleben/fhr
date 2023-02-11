<?php

$id = $_vars['providedBy.reference'][0];

$q = "select * from _HealthcareService._data a where jdoc->>'$.providedBy.reference'= '$id' limit 1";
$db->db_exec($q);
$rs = $db->db_fetch();

$hs = json_decode($rs['JDOC'], true);

foreach ($hs['coverageArea'] as $ca) {
    $caid = str_replace('Location/', '', $ca['reference']);
    $q = "select * from _Location._data where id='$caid'";
    $db->db_exec($q);
    $rs = $db->db_fetch();

    $loc = json_decode($rs['JDOC'], true);

    $data = array('resourceType' => 'Location', 'id' => $loc['id'], 'position' => $loc['position']);
    $res[] = array('resource' => $data);
}

$resourceType = 'Bundle';
$rbundle['type'] = 'searchset';

?>