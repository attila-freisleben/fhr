<?php
$idxfield = $_vars['field'][0];

$db->db_exec("select count(1) as db from _$resource._indexed_fields where field='$idxfield' ");
$rs = $db->db_fetch();
if ($rs['DB'] == 0)
    $result['body'] = "Such index ($resource.$idxfield) does not exists";
else {
    $start = microtime(true);

    $db->db_exec("select id from _$resource._indexed_fields where field='$idxfield' ");
    $rs = $db->db_fetch();
    $idxid = $rs['ID'];

    $db->db_exec("alter table _$resource._indexes drop partition idx$idxid ");
    $db->db_exec("delete from _$resource._indexed_fields where  field='$idxfield'");
    $end = microtime(true);
    $time = round($end - $start . " sec", 2);
    $result['body'] = "Index $resource.$idxfield droppped in $time";
}


?>