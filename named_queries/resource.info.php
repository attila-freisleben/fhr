<?php


$nat = $_vars['country'][0];

$grp = 10240;


// $where = isset($nat) ? "where jdoc->>'$.address[0].country'='$nat'" :"";
$where = isset($nat) ? "where id like '$nat%'" : "";

$q = "select case when jdoc->>'$.photo[0]' is not null and  jdoc->>'$.photo[0].data' is not null then 'base-resource-with-inline-photo' 
                   when jdoc->>'$.photo[0]' is not null and  jdoc->>'$.photo[0].data' is null then 'base-resource-with-photo-url'
                   else 'Base resource' end as profile,ceil(length(jdoc)/$grp)*10 datalength,count(*) count,sum(length(jdoc)) as bytes from _$resource._data  $where group by 1,2 order by 1,2";

$db->db_exec($q);

$size = 0;
$count = 0;
for (; $rs = $db->db_fetch();) {
    if (!isset($sum[$rs['PROFILE']])) {
        $sum[$rs['PROFILE']] = 0;
        $bytes[$rs['PROFILE']] = 0;
    }
    $size += $rs['BYTES'];
    $count += $rs['COUNT'];
    $sum[$rs['PROFILE']] += $rs['COUNT'];
    $bytes[$rs['PROFILE']] += $rs['BYTES'];


    $rss[] = $rs;
    $profiles[$rs['PROFILE']] = $rs['PROFILE'];
}
$i = 1;
foreach ($profiles as $key => $val) {
    $prfx[$i] = array("$i" => $val, 'COUNT' => $sum[$key], 'BYTES' => $bytes[$key]);
    $profiles[$key] = $i++;
}


foreach ($rss as $rs) {
    $prcnt = round((100 * $rs['COUNT'] / $sum[$rs['PROFILE']]), 1);

    $pid = $profiles[$rs['PROFILE']];
    $res0[$rs['DATALENGTH'] . "k"][] = array('PROFILE' => $pid, 'PERCENT' => $prcnt);
}

unset($resourceType);


$res['PROFILES'] = $prfx;
$res['DATA'] = $res0;

$rbundle['total'] = $count;
$rbundle['size'] = $size;


?>