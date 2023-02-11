<?php

$field = $_vars['field'][0];
$limit = $_vars['top'][0];
$nat = $_vars['where.field'][0];

unset($where);
foreach ($_vars as $key => $arr) {
    if (substr($key, 0, 1) == '_')
        continue;
    $where .= isset($where) ? " and " : "";
    if ($key == 'id')
        $where .= "id like '" . $arr[0] . "%'";
    else
        $where .= "jdoc->>'$.$key' like '" . $arr[0] . "%'";
}
$where = isset($where) ? "where " . $where : "";

$q = "select round(floor((year(curdate())-year(cast(jdoc->>'$.birthDate' as date)))/5),0) value,count(*) count from _$resource._data $where  group by 1 order by 1 desc ";


$db->db_exec($q);

$total = 0;
for (; $rs = $db->db_fetch();) {
    $res[] = array('resource' => array('resourceType' => 'summaryDetail', 'value' => ($rs['VALUE'] * 5) . "-" . ((($rs['VALUE'] + 1) * 5) - 1), 'count' => $rs['COUNT']));
    $total += $rs['COUNT'];
}

$rbundle['total'] = $total;

?>