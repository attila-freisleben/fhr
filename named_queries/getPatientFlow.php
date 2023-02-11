<?php

$serviceProvider = $_vars['serviceProvider'][0];
$nat = $_vars['address.country'][0];
$start = $_vars['period.start'][0];
$end = $_vars['period.end'][0];
$location = $_vars['location'][0];
$organization = $_vars['organization'][0];
$profession = $_vars['profession'][0];

if (isset($_vars['_top'][0]))
    $ascdesc = " order by cnt desc limit " . $_vars['_top'][0];

if (isset($_vars['_bottom'][0]))
    $ascdesc = " order by cnt  limit " . $_vars['_bottom'][0];


$where = "where true";

if (isset($nat))
    $where .= " and jdoc->>'$.location[0].location.reference' like 'Location/LOC.$nat.%' ";
//  $where .= " and exists (select resource_id from _Encounter._indexes where index_field=1 and _Encounter._data.id=resource_id and value_string like 'Location/LOC.$nat.%') ";

if (isset($serviceProvider))
    $where .= " and jdoc->>'$.serviceProvider.reference'='Organization/$serviceProvider' ";

if (isset($start))
    $where .= " and jdoc->>'$.period.start'>='$start' ";

if (isset($end))
    $where .= " and jdoc->>'$.period.end' <='$end' ";

if (isset($organization))
    $where .= " and jdoc->>'$.serviceProvider.reference' ='$organization' ";

if (isset($location))
    $where .= " and jdoc->>'$.location[0].location.reference' ='$location' ";

if (isset($profession))
    $where .= " and jdoc->>'$.serviceType.coding[1].code' ='$profession' ";

$q = "select jdoc->>'$.serviceType.coding[0].display' as profession,week(substr(jdoc->>'$.period.start',1,10)) as date,count(distinct id) cnt,avg(jdoc->>'$.length.value') as length,sum(length(jdoc)) as size from _Encounter._data $where group by 1,2 order by 2,1";

if (isset($organization))
    $q = "select jdoc->>'$.serviceType.coding[0].display' as profession,week(substr(jdoc->>'$.period.start',1,10)) as date,count(distinct id) cnt,avg(jdoc->>'$.length.value') as length,sum(length(jdoc)) as size from _Encounter._data $where group by 1,2 order by 2,1";

if (isset($profession))
    $q = "select jdoc->>'$.serviceProvider.display' as profession,week(substr(jdoc->>'$.period.start',1,10)) as date,count(distinct id) cnt,avg(jdoc->>'$.length.value') as length,sum(length(jdoc)) as size from _Encounter._data $where group by 1,2 order by 2,1";

if (isset($location))
    $q = "select jdoc->>'$.serviceProvider.display' as profession,week(substr(jdoc->>'$.period.start',1,10)) as date,count(distinct id) cnt,avg(jdoc->>'$.length.value') as length,sum(length(jdoc)) as size from _Encounter._data $where group by 1,2 order by 2,1";

if (isset($ascdesc))
    $q = "select * from ($q) a $ascdesc";


$db->db_exec($q);
for (; $rs = $db->db_fetch();) {
    $labels[$rs['DATE']] = $rs['DATE'];
    if (!isset($total[$rs['PROFESSION']]))
        $total[$rs['PROFESSION']] = 0;

    if (!isset($size[$rs['PROFESSION']]))
        $size[$rs['PROFESSION']] = 0;

    $total[$rs['PROFESSION']] += $rs['CNT'];
    if ($rs['LENGTH'] != 0)
        $length[$rs['PROFESSION']][] = $rs['LENGTH'];

    $size[$rs['PROFESSION']] += $rs['SIZE'];
    $prf[$rs['PROFESSION']][$rs['DATE']] = array('date' => $rs['DATE'], 'cnt' => $rs['CNT']);
}

asort($prf);

foreach ($labels as $date)
    foreach ($prf as $professionX => $arr) {
        $cnt = isset($arr[$date]) ? $arr[$date]['cnt'] : 0;
        $prf2[$professionX][] = $cnt * 1; //array('date'=>$date,'cnt'=>$cnt);
        $dura[$professionX] = round(array_sum($length[$professionX]) / count($length[$professionX]));
    }
if (!is_array($labels))
    $labels["No data"] = "No data";

$res[0]['labels'] = array_keys($labels);  //this is return value

ksort($prf2);

foreach ($prf2 as $key => $arr)
    $res[0]['datasets'][] = array('label' => $key, 'total' => $total[$key], 'length' => $dura[$key], 'size' => round($size[$key] / 1024 / 1024, 1), 'data' => $arr);   //this is return value


$q = "select profession,day,dayname,avg(cnt) as cnt from (select jdoc->>'$.serviceType.coding[0].display' as profession,week(substr(jdoc->>'$.period.start',1,10)) as week,dayofweek(substr(jdoc->>'$.period.start',1,10)) as day,dayname(substr(jdoc->>'$.period.start',1,10)) as dayname,count(distinct id) as cnt from _Encounter._data $where group by 1,2,3,4 ) a group by profession,day,dayname order by 1,2";

if (isset($organization))
    $q = "select profession,day,dayname,avg(cnt) as cnt from (select jdoc->>'$.serviceType.coding[0].display' as profession,week(substr(jdoc->>'$.period.start',1,10)) as week,dayofweek(substr(jdoc->>'$.period.start',1,10)) as day,dayname(substr(jdoc->>'$.period.start',1,10)) as dayname,count(distinct id) cnt from _Encounter._data $where group by 1,2,3 ,4 ) a group by profession,day,dayname order by 1,2";

if (isset($profession))
    $q = "select profession,day,dayname,avg(cnt) as cnt from (select jdoc->>'$.serviceProvider.display' as profession,week(substr(jdoc->>'$.period.start',1,10)) as week,dayofweek(substr(jdoc->>'$.period.start',1,10)) as day,dayname(substr(jdoc->>'$.period.start',1,10)) as dayname,count(distinct id) cnt from _Encounter._data $where group by 1,2,3 ,4 ) a group by profession,day,dayname order by 1,2";

if (isset($location))
    $q = "select profession,day,dayname,avg(cnt) as cnt from (select jdoc->>'$.serviceProvider.display' as profession,week(substr(jdoc->>'$.period.start',1,10)) as week,dayofweek(substr(jdoc->>'$.period.start',1,10)) as day,dayname(substr(jdoc->>'$.period.start',1,10)) as dayname,count(distinct id) cnt from _Encounter._data $where group by 1,2,3,4 ) a group by profession,day,dayname order by 1,2";

if (isset($ascdesc))
    $q = "select * from ($q) a $ascdesc";

unset($profs);
$db->db_exec($q);
for (; $rs = $db->db_fetch();) {
    $profs[$rs['PROFESSION']] = $rs['PROFESSION'];
    $dlabels[$rs[DAY]] = $rs[DAYNAME];
    $prfd[$rs['PROFESSION']][$rs['DAY']] = array('cnt' => $rs['CNT']);
}

ksort($dlabels);

foreach ($dlabels as $key => $day)
    foreach ($prfd as $professionX => $arr) {
        $cnt = isset($arr[$key]) ? $arr[$key]['cnt'] : 0;
        $prfd2[$professionX][] = $cnt * 1;
    }

foreach ($prfd2 as $professionX => $arr) {
    $prfd3[] = array('label' => $professionX, 'data' => $arr);
}


$dlabels = array_values($dlabels);
if (!is_array($dlabels)) {
    $dlabels[] = "No data";
    $prfd3[] = "No data";
}

$res[0]['daily'] = array('labels' => $dlabels, 'data' => $prfd3);   //this is return value

$res[0]['resourceType'] = 'grmPatientFlow';

?>