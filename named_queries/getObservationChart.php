<?php

$country = $_vars['subject.address[0].country'][0];

$q = "select * from xfhir._resources where resource='Encounter'";
$db->db_exec($q);
$rs = $db->db_fetch();
$encrr = $rs['ID'];

$q = "select * from xfhir._resources where resource='Patient'";
$db->db_exec($q);
$rs = $db->db_fetch();
$patrr = $rs['ID'];

$q = "select * from _Patient._indexed_fields where field='address[0].country'";
$db->db_exec($q);
$rs = $db->db_fetch();
$pati = $rs['ID'];

if ($country == "")
    $q = "select _Encounter_data0.jdoc->>'$.serviceType.coding[1].code' as code,_Encounter_data0.jdoc->>'$.serviceType.coding[1].display' as serviceType,count(0) as value
         from _Observation._data _data0 
         join _Observation._references _ref0 on (_ref0.ref_resource=$encrr  and _ref0.resource_id=_data0.id ) 
         join _Encounter._data _Encounter_data0 on (true  and _ref0.ref_id=_Encounter_data0.id )  
         group by 1,2 order by 2";
else {
    if ($pati == "")
        $q = "select _Encounter_data0.jdoc->>'$.serviceType.coding[1].code' as code,_Encounter_data0.jdoc->>'$.serviceType.coding[1].display' as serviceType,count(0) as value
       from _Observation._data _data0 
       join _Observation._references _ref0 on (_ref0.ref_resource=$encrr  and _ref0.resource_id=_data0.id ) 
       join _Encounter._data _Encounter_data0 on (true  and _ref0.ref_id=_Encounter_data0.id )  
       join _Observation._references _ref1 on (_ref1.ref_resource=$patrr  and _ref1.resource_id=_data0.id ) 
       join _Patient._data _Patient_data1 on (true  and _ref1.ref_id=_Patient_data1.id and _Patient_data1.jdoc->>'$.address[0].country'='$country')  
       group by 1,2 order by 2";
    else
        $q = "select _Encounter_data0.jdoc->>'$.serviceType.coding[1].code' as code,_Encounter_data0.jdoc->>'$.serviceType.coding[1].display' as serviceType,count(0) as value
       from _Observation._data _data0 
       join _Observation._references _ref0 on (_ref0.ref_resource=$encrr  and _ref0.resource_id=_data0.id ) 
       join _Encounter._data _Encounter_data0 on (true  and _ref0.ref_id=_Encounter_data0.id )  
       join _Observation._references _ref1 on (_ref1.ref_resource=$patrr  and _ref1.resource_id=_data0.id ) 
       join _Patient._data _Patient_data1 on (true  and _ref1.ref_id=_Patient_data1.id )  
       join _Patient._indexes _Patient_data1i19 use index(key_string)  on ( _Patient_data1i19.index_field=$pati  and _Patient_data1i19.value_string='$country'   and _Patient_data1.id=_Patient_data1i19.resource_id )
       group by 1,2 order by 2";
}

//echo $q;
$db->db_exec($q);

$total = 0;
for (; $rs = $db->db_fetch();) {
    $res[] = array('resource' => array('resourceType' => 'summaryDetail', 'code' => $rs['CODE'], 'display' => $rs['SERVICETYPE'], 'count' => $rs['VALUE']));
    $total += $rs['VALUE'];
}

$rbundle['total'] = $total;

?>