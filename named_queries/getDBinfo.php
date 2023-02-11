<?php


$q = "select a2,name from $rangeDB.iso3166 order by 1";
$db->db_exec($q);
for (; $rs = $db->db_fetch();) {
    $countries[$rs['A2']] = $rs['NAME'];
}


$q = "select jdoc->>'$.address[0].country' country,count(0) as db from _Organization._data group by 1 order by 1";
$db->db_exec($q);
for (; $rs = $db->db_fetch();) {
    $orgs[$rs['COUNTRY']] = $rs['DB'];
}

$q = "select substr(id,1,2) as country,count(0) as db from _Patient._data group by 1 order by 1";
$db->db_exec($q);
for (; $rs = $db->db_fetch();) {
    $pats[$rs['COUNTRY']] = $rs['DB'];
}

$q = "select substr(id,5,2) as country,count(0) as db from _Encounter._data group by 1 order by 1";
$db->db_exec($q);
for (; $rs = $db->db_fetch();) {
    $encs[$rs['COUNTRY']] = $rs['DB'];
}

$q = "select substr(id,6,2) as country,count(0) as db from _Condition._data group by 1 order by 1";
$db->db_exec($q);
for (; $rs = $db->db_fetch();) {
    $conds[$rs['COUNTRY']] = $rs['DB'];
}

$q = "select substr(id,5,2) as country,count(0) as db from _Observation._data group by 1 order by 1";
$db->db_exec($q);
for (; $rs = $db->db_fetch();) {
    $obs[$rs['COUNTRY']] = $rs['DB'];
}

$q = "select substr(id,4,2) as country,count(0) as db from _HealthcareService._data group by 1 order by 1";
$db->db_exec($q);
for (; $rs = $db->db_fetch();) {
    $hcss[$rs['COUNTRY']] = $rs['DB'];
}

$q = "select substr(id,5,2) as country,count(0) as db from _Location._data group by 1 order by 1";
$db->db_exec($q);
for (; $rs = $db->db_fetch();) {
    $locs[$rs['COUNTRY']] = $rs['DB'];
}

$q = "select substr(id,1,2) as country,count(0) as db from _Practitioner._data group by 1 order by 1";
$db->db_exec($q);
for (; $rs = $db->db_fetch();) {
    $pracs[$rs['COUNTRY']] = $rs['DB'];
}

$q = "select substr(id,5,2) as country,count(0) as db from _PractitionerRole._data group by 1 order by 1";
$db->db_exec($q);
for (; $rs = $db->db_fetch();) {
    $pracrs[$rs['COUNTRY']] = $rs['DB'];
}

foreach ($orgs as $key => $val) {
    $data['resourceType'] = 'summaryDetail';
    $data['CountryCode'] = $key;
    $data['Country'] = $countries[$key];
    $data['Organization'] = $val;
    $data['HealthcareService'] = $hcss[$key];
    $data['Location'] = $locs[$key];
    $data['Practitioner'] = $pracs[$key];
    $data['PractitionerRole'] = $pracrs[$key];
    $data['Patient'] = $pats[$key];
    $data['Encounter'] = $encs[$key];
    $data['Conditions'] = $conds[$key];
    $data['Observations'] = $obs[$key];
    $res[] = array('resource' => $data);
}

$resourceType = 'Bundle';
$rbundle['type'] = 'searchset';

?>