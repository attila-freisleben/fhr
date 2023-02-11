<?php


function updateRef($db, $basedb, $t_resource, $resource, $id, $key, $val)
{
    if (array_key_exists('reference', $val)) {
        $refid = $val['reference'];
        $rt = getFHIRReferenceType($resource, $key);
        $reftype = $rt[0];

        if ($reftype != "") {
            if ($reftype == "")
                throw new Exception("Reference t�pus hi�nyzik");
            $db->db_exec("select * from $basedb._resources where resource='$resource'");
            $rsr = $db->db_fetch();
            $resource_id = $rsr['ID'];

            $db->db_exec("select * from $basedb._resources where resource='$reftype'");
            $rsr = $db->db_fetch();
            $reference_id = $rsr['ID'];

            $refid = str_replace('urn:uuid:', '', $refid);
            $refid = str_replace($reftype . "/", '', $refid);

            $db->db_exec("insert ignore into $t_resource._references(ref_resource,resource_id,ref_id) values ($reference_id,'$id','$refid')");
        }
    } else {
        foreach ($val as $varr)
            updateRef($db, $basedb, $t_resource, $resource, $id, $key, $varr);
    }
}


function getFHIRReferenceType($resource, $field, $ver = "")
{

    global $db, $db2, $basedb, $t_resource;
    $reftypes = array();
    $db->db_exec("select * from _$resource._reference_types a join $basedb._resources b on (a.ref_resource_type=b.id) where field='$field'");
    $rs = $db->db_fetch();
    if ($rs['FIELD'] == '') {

        if ($ver == "R3")
            $ver = "STU3/";
        $vv = $ver . "/";
        if ($ver == "")
            $vv = "R4/";
        $profile = "./fhir/$vv$resource.profile.json";
        $json_struct = file_get_contents($profile);

        if ($json_struct == "") {
            $resurl = "http://www.hl7.org/fhir/$ver$resource.profile.json";
            $json_struct = file_get_contents_https($resurl);
            file_put_contents($profile, $json_struct);
        }

        $arr = json_decode($json_struct, true);

        foreach ($arr['snapshot']['element'] as $entry) {
            if ($entry['id'] == "$resource.$field") {
                foreach ($entry['type'] as $type)
                    if ($type['code'] == 'Reference')
                        $targets = $type['targetProfile'];
                break;
            }
        }


        $reftypes = array();
        foreach ($targets as $target) {
            $e = explode('/', $target);
            $reftypes[] = array_pop($e);
        }
        foreach ($reftypes as $reftype) {
            $db->db_exec("select * from $basedb._resources where resource='$reftype'");
            $rsr = $db->db_fetch();
            $reference_id = $rsr['ID'];
            $db->db_exec(" SELECT count(1) as db FROM information_schema.partitions WHERE TABLE_SCHEMA='$t_resource' AND TABLE_NAME = '_references' AND partition_description='$reference_id' ");
            $rs = $db->db_fetch();
            if ($rs['DB'] == 0) {

                $db->db_exec("select count(0) as db from $basedb._resources where resource='$reftype'");
                $rs = $db->db_fetch();
                if ($rs['DB'] == 0) {
                    require_once("archetype/crdb.php");
                    crdb($reftype);
                    $db->db_exec("select * from $basedb._resources where resource='$reftype'");
                    $rsr = $db->db_fetch();
                    $reference_id = $rsr['ID'];
                }
                $db2->db_exec("update $basedb._resources set locked=true where resource='$resource'");
                $db2->db_exec("alter table $t_resource._references add partition (PARTITION r$reference_id VALUES IN ($reference_id) ENGINE = InnoDB)");
                $db2->db_exec("update $basedb._resources set locked=false where resource='$resource'");
            } //information_schema.partitions

            $db->db_exec("insert ignore into $t_resource._reference_types values ('$reference_id','$field')");
        }
    } else
        $reftypes[] = $rs['RESOURCE'];

    if ($ver == "" && count($reftypes) == 0)
        getFHIRReferenceType($resource, $field, "STU3");

    return $reftypes;
}


/************ checks **************/
/*** check methods vs params */

$cmd = $_vars['_cmd'][0];
if ($cmd != "") {
    require "$cmds_dir/$cmd.php";
    goto ten;
}


if ($method == 'PATCH' && $id == "") {
    $result[0]['status'] = '405';
    $result[0]['body'] = json_encode(array(utf8_encode("ID required for PATCH operations" . json_last_error_msg())));
    goto ten;
}

/*** validate FHIR profile */
$jsonarr['meta']['noprofilecheck'] = true;

if (!$jsonarr['meta']['noprofilecheck'] == "true") {
    $profile = $jsonarr['meta']['profile'] != "" ? $jsonarr['meta']['profile'] : $hl7fhirBase . $resource;
    $validate = validateResource($jdoc, $resource, $profile);

    if (!$validate['valid'] == 0) {
        $result['status'] = '422';
        $result['body'] = $validate['text'];
        goto ten;
    }
} //noprofilecheck
/************ checks end *****************/

start:

$db->db_exec("select count(0) as db from $basedb._resources where resource='$resource'");
$rs = $db->db_fetch();
if ($rs['DB'] == 0) {
    require_once("archetype/crdb.php");
    crdb($resource);
}

if ($id == "")
    $id = $jsonarr['id'];
$urc = $id == "" || !isset($id);

$t_resource_history = $t_resource . "_history";

/********************* maintain version & history */
// $jdoc=addslashes($jdoc);
if (false)
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

if ($id == "") {
    $id = getkey($t_resource);
    echo "GETKEY:$id";
}


$meta['versionId'] = $version;
$meta['lastUpdated'] = date("Y-m-d") . "T" . date("H:i:sP");
$mj = stripslashes(json_encode($meta));
mysqli_set_charset($db->db_conn, 'UTF-8');
$jdocq = mysqli_real_escape_string($db->db_conn, $jdoc);


if ($method == 'PATCH') {
    $db->db_exec("insert into $t_resource._data(id,jdoc) values('$id','$jdocq') on duplicate key update jdoc=json_merge_patch(jdoc,'$jdocq'), version=version+1 ");
} else {
    $db->db_exec("insert into $t_resource._data(id,jdoc) values('$id','$jdocq') on duplicate key update jdoc='$jdocq', version=version+1 ");
}
//  if($urc)
//      $db->db_exec("update $basedb._resources set rowcount=rowcount+1 where resource='$resource' ");


/*********************  maintain resource meta info */
$db->db_exec("update $t_resource._data set jdoc=json_merge_patch(json_merge_patch(jdoc,'{\"meta\" : $mj }'),'{\"id\":\"$id\"}') where id='$id'");

/********************* maintain indexes */
$db->db_exec("select * from $t_resource._indexed_fields ");
for (; $rs = $db->db_fetch();) {
    $idxid = $rs['ID'];

    $idxfield = $rs['FIELD'];
    $idxtype = $rs['DATATYPE'];

    $isArray = strpos($idxfield, "[*]");

    if ($isArray) {
        $idxfield0 = explode('[*]', $idxfield);
        $db2->db_exec("select max(json_length(jdoc->>'$." . $idxfield0[0] . "')) as arc from $t_resource._data where id='$id' and jdoc->>'$.$idxfield' is not null ");
        $rs = $db2->db_fetch();
        $arc = $rs['ARC'];
        for ($i = 0; $i < $arc; $i++) {
            $idxf = str_replace("[*]", "[$i]", $idxfield);
            $db2->db_exec("insert into $t_resource._indexes (index_field,resource_id,array_id,value_$idxtype) select $idxid,'$id',$i,jdoc->>'$.$idxf' from $t_resource._data where id='$id' and jdoc->>'$.$idxf' is not null on duplicate key update value_$idxtype=jdoc->>'$.$idxf'   ");
        }
    } else
        $db2->db_exec("insert into $t_resource._indexes (index_field,resource_id,array_id,value_$idxtype) select $idxid,'$id',0,jdoc->>'$.$idxfield' from $t_resource._data where id='$id' and jdoc->>'$.$idxfield' is not null on duplicate key update value_$idxtype=jdoc->>'$.$idxfield'   ");
}


/********************** maintain references */
$arr = json_decode($jdoc, true);
foreach ($arr as $key => $val) {
    updateRef($db, $basedb, $t_resource, $resource, $id, $key, $val);
    /*
     if(is_array($val))
      if(array_key_exists('reference',$val))
       {
         $refid   = $val['reference'];
         $rt = getFHIRReferenceType($resource,$key);
         $reftype=$rt[0];

    if($reftype!="")
        {
         if($reftype=="")
                   throw new Exception("Reference t�pus hi�nyzik");
         $db->db_exec("select * from $basedb._resources where resource='$resource'");
         $rsr = $db->db_fetch();
         $resource_id=$rsr['ID'];

         $db->db_exec("select * from $basedb._resources where resource='$reftype'");
         $rsr = $db->db_fetch();
         $reference_id=$rsr['ID'];

          $refid=str_replace('urn:uuid:','',$refid);
          $refid=str_replace($reftype."/",'',$refid);

          $db->db_exec("insert ignore into $t_resource._references(ref_resource,resource_id,ref_id) values ($reference_id,'$id','$refid')");
         }
    }
   */
}


/********************* process outcome */
/*** todo: explain a bit more */

$db->db_exec("select jdoc from $t_resource._data where id='$id'");
$rs = $db->db_fetch();
$res = json_decode($rs['JDOC'], true);

if ($rs['JDOC'] != "") {
    $result['status'] = '200';
    $result['body'] = $res;
} else {
    file_put_contents('post.log', "$jdoc", FILE_APPEND);
    goto start;
    $result['status'] = '500';
    $result['body'] = 'Internal server error: Something went wrong';
}
unset($id);

ten:

?>