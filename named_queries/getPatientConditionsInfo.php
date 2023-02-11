<?php

$patient_id = str_replace('Patient/', '', $_vars['subject.reference'][0]);


$q = "select * from xfhir._resources where resource='Encounter'";
$db->db_exec($q);
$rs = $db->db_fetch();
$encrr = $rs['ID'];

$q = "select * from xfhir._resources where resource='Patient'";
$db->db_exec($q);
$rs = $db->db_fetch();
$patrr = $rs['ID'];

$q = "select _data0.jdoc->>'$.code.coding[1].code' as code, _data0.jdoc->>'$.code.coding[1].display' as display,count(0) as value,
       min(_Encounter_data0.jdoc->>'$.period.start') as min_period_start,
       max(_Encounter_data0.jdoc->>'$.period.start') as max_period_start
       from _Condition._data _data0 
       join _Condition._references _ref0 on (_ref0.ref_resource=$encrr  and _ref0.resource_id=_data0.id ) 
       join _Encounter._data _Encounter_data0 on (true  and _ref0.ref_id=_Encounter_data0.id )  
       join _Condition._references _ref1 on (_ref1.ref_resource=$patrr  and _ref1.resource_id=_data0.id ) 
       join _Patient._data _Patient_data1 on (true  and _ref1.ref_id=_Patient_data1.id and _Patient_data1.id='$patient_id')  
       group by 1,2 order by 2";

if ($patient_id != "") {
    $db->db_exec($q);

    $total = 0;
    for (; $rs = $db->db_fetch();) {
        $res[] = array('resource' => array('resourceType' => 'summaryDetail', 'code' => $rs['CODE'], 'display' => $rs['DISPLAY'], 'count' => $rs['VALUE'], 'min_date' => $rs['MIN_PERIOD_START'], 'max_date' => $rs['MAX_PERIOD_START']));
        $total += $rs['VALUE'];
    }

    $rbundle['total'] = $total;
}

?>