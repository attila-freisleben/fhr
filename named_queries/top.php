<?php

$field = $_vars['field'][0];
$limit = $_vars['top'][0];
$wherefield = $_vars['where.field'][0];
$wherevalue = $_vars['where.value'][0];

if (isset($wherefield) && isset($wherevalue))
    $where = " where jdoc->>'$.$wherefield'='$wherevalue' ";

$q = "select jdoc->>'$." . $field . "' as value,count(0) as count from _$resource._data $where  group by jdoc->>'$." . $field . "' order by 2 desc limit $limit";

$db->db_exec($q);

for (; $rs = $db->db_fetch();) {
    $res[] = $rs;
}


?>