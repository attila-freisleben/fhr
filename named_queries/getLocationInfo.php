<?php

$where = $_vars['address[0].country'][0] == "" ? "" : " where id like 'HS." . $_vars['address[0].country'][0] . "%'";

$limit = $_vars['_count'][0] == "" ? "" : " limit " . $_vars['_count'][0];

$q = "select a.*,b.name from (select distinct substr(id,4,2) as country, jdoc->>'$.providedBy.display' org,jdoc->>'$.providedBy.reference' org_reference,  json_length(jdoc->>'$.coverageArea') as db,jdoc->>'$.location[0].reference' as locref from _HealthcareService._data $where) a join fhiringRange.iso3166 b where a2=a.country order by 1,2 $limit";
$db->db_exec($q);
for (; $rs = $db->db_fetch();) {
    $rs['LOCREF'] = str_replace('Location/', '', $rs['LOCREF']);
    $db2->db_exec("select jdoc->'$.position' as position from _Location._data where id='" . $rs['LOCREF'] . "'");
    $rs2 = $db2->db_fetch();
    $rs2['POSITION'] = json_decode($rs2['POSITION'], true);

    $data = array('resourceType' => 'summaryDetail', 'countryCode' => $rs['COUNTRY'], 'country' => $rs['NAME'], 'organization' => $rs['ORG'], 'org_reference' => $rs['ORG_REFERENCE'], 'position' => $rs2['POSITION'], 'count' => $rs['DB']);
    $res[] = array('resource' => $data);
}

$resourceType = 'Bundle';
$rbundle['type'] = 'searchset';

?>