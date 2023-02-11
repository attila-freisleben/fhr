<?php

$idxfield = $_vars['field'][0];
$datatype = $_vars['datatype'][0]; //'string','integer','double','datetime'

$datatypes = array('string', 'integer', 'double', 'datetime');
if (!in_array($datatype, $datatypes)) {
    $result['body'] = "Invalid datatype: '$datatype'; Valid types are: string, integer, double, datetime";
} else {
    $dt_value = "value_" . $datatype;
    $isArray = strpos($idxfield, "[*]");

    $db->db_exec("select count(1) as db from $t_resource._indexed_fields where field='$idxfield' ");
    $rs = $db->db_fetch();
    if ($rs['DB'] != 0)
        $result['body'] = 'Such index ($resource.$idxfield) already exists';
    else {
        $start = microtime(true);
        // notify inserts to maintain index even if it's not yet populated here
        $db->start_transaction();
        $db->db_exec("insert into $t_resource._indexed_fields(field,datatype,locked) values ('$idxfield','$datatype',1)");
        $db->commit();
        $db->start_transaction();
        $db->db_exec("select id from $t_resource._indexed_fields where  field='$idxfield' ");
        $rs = $db->db_fetch();

        $idxid = $rs['ID'];
        if ($idxid > 0)
            $db2->db_exec("alter table $t_resource._indexes add partition (partition idx$idxid values in ($idxid) engine=innodb)");

        //flatten array data
        if ($isArray) {
            $idxfield0 = explode('[*]', $idxfield);
            $arc = 0;
            //find maximum array dimension
            do {
                $arc++;
                $db->db_exec("select 1 as db from $t_resource._data where jdoc->>'$." . $idxfield0[0] . "[$arc]' is not null limit 1");
                $rs = $db->db_fetch();
            } while ($rs['DB'] == 1);

            for ($i = 0; $i < $arc; $i++) {
                $idxf = str_replace("[*]", "[$i]", $idxfield);
                $db->db_exec("insert ignore into $t_resource._indexes (index_field,$dt_value,resource_id,array_id) select $idxid,jdoc->>'$.$idxf',id,$i from $t_resource._data where trim(jdoc->>'$.$idxf') is not null on duplicate key update $dt_value=jdoc->>'$.$idxf' ");
            }
        } else
            $db->db_exec("insert ignore into $t_resource._indexes (index_field,$dt_value,resource_id,array_id) select $idxid,jdoc->>'$.$idxfield',id,0 from $t_resource._data where trim(jdoc->>'$.$idxfield') is not null on duplicate key update $dt_value=jdoc->>'$.$idxfield' ");

        $db->db_exec("update $t_resource._indexed_fields set locked=0 where field='$idxfield' ");
        $db->commit();
        $end = microtime(true);
        $time = round($end - $start . " sec", 2);
        $result['body'] = "Index $resource.$idxfield created in $time";

    }
} //in_array(datatype)

?>