<?php

$t_resource_history = $t_resource . "_history";

/********************* maintain version & history */
// $jdoc=addslashes($jdoc);
if (TRUE)
    if ($id !== "") {
        $db->db_exec("insert into $t_resource._data_history select * from $t_resource._data where id='$id'");
        $db->db_exec("select version from $t_resource._data where id='$id'");
        $rs = $db->db_fetch();
        $version = $rs['VERSION'] + 1;
    }

if ($version == "")
    $version = 1;

/********************* maintain table data */
/*** assign ID if needed */

if ($method == 'DELETE') {
    $db->db_exec("delete from $t_resource._data where id='$id' ");
    $db->db_exec("delete from $t_resource._indexes where resource_id='$id' ");
    $db->db_exec("delete from $t_resource._references where resource_id='$id' ");
}


/********************* process outcome */
/*** todo: explain a bit more */

$db->db_exec("select jdoc from $t_resource._data where id='$id'");
$rs = $db->db_fetch();
$res = json_decode($rs['JDOC'], true);

if ($rs['JDOC'] == "") {
    $result['status'] = '200';
    $result['body'] = $res;
} else {
    file_put_contents('post.log', "$jdoc", FILE_APPEND);
    $result['status'] = '500';
    $result['body'] = 'Internal server error: Something went wrong';
}
unset($id);

?>