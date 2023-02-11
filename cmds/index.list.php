<?php
$db->db_exec("select * from _$resource._indexed_fields order by field ");
$res['resource'] = $resource;

for (; $rs = $db->db_fetch();) {
    $locked = $rs['LOCKED'] == 0 ? 'in use' : 'locked';
    $res['indexes'][] = array("indexField" => $rs['FIELD'], "status" => $locked);
}
if (count($res['indexes']) == 0)
    $res['indexes'] = array();

$result['body'] = $res;


?>