<?php

$debugs = array();
$debug = $_vars['_debug'][0];

function add_debug($key, $str)
{
    global $debugs;
    $debugs[] = array($key => $str);
}

//require_once("searchparams.php");

function getResourceByID($resource, $id, $db)
{
    $db->db_exec("select jdoc from _$resource._data where id='$id'");
    $rs = $db->db_fetch();
    return $rs['JDOC'];
}

function getRefResourceTypeByField($resource, $field, $db, $basedb)
{
    $db->db_exec("select resource from $basedb._resources a join _$resource._reference_types b on (a.id=b.ref_resource_type) where field='$field'");
    $rs = $db->db_fetch();
    return $rs['RESOURCE'];
}



function getAllReferencingResources($resource, $id, $db, $basedb)
{
    $db->db_exec("select id from $basedb._resources where resource='$resource'");
    $rs = $db->db_fetch();
    $rsid = $rs['ID'];

    $db->db_exec("select id,resource from $basedb._resources order by id");
    for (; $rs = $db->db_fetch();)
        $rrs[] = $rs;

    foreach ($rrs as $ars) {
        $db->db_exec("select '" . $ars['RESOURCE'] . "' as resource,resource_id from  _" . $ars['RESOURCE'] . "._references where ref_resource=$rsid and ref_id='$id' order by resource_id");
        for (; $rs = $db->db_fetch();) {
            if (is_array($rs))
                $rfs[] = $rs;
        }
    }
    return $rfs;
}


function getFHIRCardinality($resource, $field, $ver = "")
{
    global $db, $db2, $basedb, $t_resource, $basedir;
    $reftypes = array();
    $db->db_exec("select * from _$resource._reference_types a join $basedb._resources b on (a.ref_resource_type=b.id) where field='$field'");
    $rs = $db->db_fetch();
    if ($rs['FIELD'] == '') {

        if ($ver == "R3")
            $ver = "STU3/";
        $vv = $ver . "/";
        if ($ver == "")
            $vv = "R4/";
        $profile = "./fhir/REST/fhir/$vv$resource.profile.json";
        $json_struct = file_get_contents($profile);

        if ($json_struct == "") {
            $resurl = "http://www.hl7.org/fhir/$ver$resource.profile.json";
            $json_struct = file_get_contents_https($resurl);
            file_put_contents($profile, $json_struct);
        }

        $arr = json_decode($json_struct, true);
        $card = array("type" => "", "min" => 0, "max" => 0);
        foreach ($arr['snapshot']['element'] as $entry) {
            if ($entry['id'] == "$resource.$field") {
                $card = array("type" => $entry['type'], "min" => $entry['min'], "max" => $entry['max']);
                break;
            }
        }
    }
    return $card;
}

function checkResourceExists($resource, $db, $basedb)
{
    $db->db_exec("select count(*) as db from $basedb._resources where resource='$resource'");
    $rs = $db->db_fetch();
    return $rs['DB'] != 0;
}

if (!checkResourceExists($resource, $db, $basedb)) {
    $res['resourceType'] = 'OperationOutcome';
    $res['issue']['details'] = "Resource '$resource' does not exists";
    $res['issue']['severity'] = 'warning';
    $res['issue']['code'] = 'not-found';
    $result['status'] = '200';
    $result['body'] = $res;
    goto get_end;
}


class _resource
{
    public $alias, $cc;
    private $id, $indexes, $index_datatype, $resource, $t_resource, $db, $app_low, $app_high, $cond_prefix, $reftable, $conditionsUsedInIndex, $conditionsUsedInReference, $orderByAlias, $noOfReferences;
    private $usedReferences;


    function __construct($name)
    {
        global $db, $app_low, $app_high, $cond_prefix, $basedb;

        $this->basedb = $basedb;
        $this->resource = $name;
        $this->t_resource = "_" . $name;
        $this->alias = "_data0"; //$this->t_resource."._data";
        $this->reftable = $this->t_resource . "._references";
        $this->db = $db;
        $this->app_low = $app_low;
        $this->app_high = $app_high;
        $this->cond_prefix = $cond_prefix;
        $this->id = $this->getResourceID();

        $this->indexes = $this->getIndexes();

        $this->csql = array();
        $this->dts = array();


        $this->noOfReferences['ref'] = 0;
        $this->noOfReferences['data'] = 0;
        $this->noOfReferences['refdata'] = 0;

        $this->usedReferences = array();
        $this->conditionsUsedInIndex = array();
        $this->conditionsUsedInReference = array();
    }

    function getResourceID()
    {
        $basedb = $this->basedb;
        $this->db->db_exec("select * from $basedb._resources where resource='" . $this->resource . "'");
        $rs = $this->db->db_fetch();
        return $rs['ID'];
    }

    function getIndexes()
    {
        $this->db->db_exec("select * from " . $this->t_resource . "._indexed_fields where locked=0");
        for (; $rs = $this->db->db_fetch();)
            $indexes[$rs['FIELD']] = $rs['ID'];
        return $indexes;
    }

    function getStraightJoin($resources)
    {
        foreach ($resources as $resource) {
            $schemas .= "'_$resource',";
        }

        if ($schemas != "") {
            $schemas = substr($schemas, 0, -1);
            $this->db->db_exec("select * from information_schema.tables where table_name='_data' and table_schema in ($schemas) order by table_rows");
            $rs = $this->db->db_fetch();
        }
        return $rs['TABLE_SCHEMA'] . "." . $rs['TABLE_NAME'];
    }


    function getOrderBy($sql)
    {
        {
            $sql = "explain $sql";
            $this->db->db_exec($sql);

            do {
                $rs = $this->db->db_fetch();
            } while (substr($rs['TABLE'], 0, 1) == '<');
            return $rs['TABLE'];
        }
    }


    function collectIndexedResources($keys)
    {
        foreach ($keys as $key) {
            $arr = $this->getField($key);
            if (is_array($arr)) {
                $rname = $this->getResourceName($arr['resourceType']);
                $rr = new _resource($rname);
                $field = $arr['field'];
                if ($rr->indexExists($field)) {
                    $idxs[$rname][$field] = [$field];
                }
            }
        }

        foreach ($idxs as $ikey => $ival)
            $indexs["$ikey"] = array_keys($ival);

        return $indexs;
    }


    public function getResourceName($id)
    {
        $basedb = $this->basedb;
        $this->db->db_exec("select * from $basedb._resources where id='$id'");
        $rs = $this->db->db_fetch();
        return $rs['RESOURCE'];
    }

    public function indexExists($key)
    {
        if (!is_array($this->indexes))
            $this->indexes = $this->getIndexes();
        return array_key_exists($key, $this->indexes);
    }


    public function collectReferencedResources($keys)
    {
        //eg.: condition->subject.generalPractitioner.gender=female
        foreach ($keys as $key) {
            $arr[$key] = array_pop($this->getReference($key));
            $kk = explode('.', $key);
            foreach ($kk as $key2) {
                $arr[$key2] = array_pop($this->getReference($key2));
            }
        }

        foreach ($arr as $key => $val) {
            $r = $this->getResourceName($val);
            if (isset($r))
                $ret[$r] += 1;
        }

        return $ret;
    }


    public function collectOrderByResources($keys)
    {
        foreach ($keys as $key) {
            $arr = $this->getField($key);
            if (is_array($arr)) {
                $rname = $this->getResourceName($arr['resourceType']);
                $ret[$rname] = $arr['resourceType'];
            }
        }
        return $ret;
    }


    public function getConditionsUsedInIndex()
    {
        return $this->conditionsUsedInIndex;
    }


    public function useJoinIndex($key, $val, $rdtalias = "")
    {
        if ($this->indexExists($key)) {
            $key_type = "key_" . $this->getResourceTypeFromIndex($key);
            $i = $this->indexes[$key];
            $ii = $rdtalias . "i";
            $icondi = $this->insertConditionToIndex($key, $val, $i, $ii);
            $iand = trim($icondi) === "" ? "" : " and ";
            $index = " join " . $this->t_resource . "._indexes $ii$i use index($key_type)  on ( $ii$i.index_field=$i $iand $icondi and " . $rdtalias . ".id=$ii$i.resource_id ) ";
        }
        return $index;
    }


    public function getResourceTypeFromIndex($field)
    {
        $this->db->db_exec("select * from " . $this->t_resource . "._indexed_fields where field='$field'");
        $rs = $this->db->db_fetch();
        return $rs['DATATYPE'];
    }

    public function insertConditionToIndex($key, $val, $i, $ii)
    {
        $oper = substr($val, 0, 2);
        $vtype = $this->getResourceTypeFromIndex($key);
        $value_type = "value_" . $vtype;

        if ($vtype == "string")
            $vtype = "";

        $this->conditionsUsedInIndex["$key"]["$val"] = true;
        if (array_key_exists($oper, $this->cond_prefix)) {
            if (is_nan($val))
                $val = "'" . str_replace("'", "", $val) . "'";
            switch ($oper) {
                case 'ap':
                {
                    $val = substr($val, 2);
                    if ($vtype != "")
                        $cond = " $ii$i.$value_type between cast(" . ($val * $this->app_low) . " as $vtype) and cast(" . ($val * $this->app_high) . " as $vtype)";
                    else
                        $cond = " $ii$i.$value_type between " . ($val * $this->app_low) . " and " . ($val * $this->app_high) . " ";
                    break;
                }
                case 'bt':
                {
                    $val = substr($val, 2);
                    $vals = explode('|', $val);
                    if (count($vals) != 2) {
                        return array('error' => 'Invalid bt operation');
                    }
                    if ($vtype != "")
                        $cond = " $ii$i.$value_type between cast('" . $vals[0] . "' as $vtype) and cast('" . $vals[1] . "' as $vtype)";
                    else
                        $cond = " $ii$i.$value_type between '" . $vals[0] . "'  and '" . $vals[1] . "' ";
                    break;
                }
                default:
                {
                    if (strpos($val, '%') !== false)
                        $cond = "$ii$i" . $value_type . " like '$val' ";
                    else
                        if ($vtype != "")
                            $cond = "$ii$i." . $value_type . " " . $this->cond_prefix[$oper] . " cast('" . substr($val, 2) . "' as $vtype)";
                        else
                            $cond = "$ii$i." . $value_type . " " . $this->cond_prefix[$oper] . " '" . substr($val, 2) . "' ";

                }
            } //switch
            $icond .= " $and $value $cond";
        }  //array_key_exists
        else
            if (strpos($val, '|') !== false) {  //ezt ha esetleg bekommentezném, hogy ez mi a rák,  köszönöm...
                /**  ha a keresett érték érték1|érték2|.. formában van, akkor and-el keresi pl. code.coding[*]=http://bno.xfhir.net|10001,
                 * akkor csak az adott kódrendszer szerinti kódokat code.coding[x].system='http://bno.xfhir.net' and code.coding[x].code='10001'
                 * illetve hát ha a stringben mindkettő megvan, mivel mező az nincs, majd talán egyszer...*/
                $vals = explode('|', $val);
                $pf = 0;
                if (strpos($key, '*') !== false) {
                    $skey1 = "->>'$.$key'";
                    $skey2 = "";
                } else {
                    $skey2 = ",NULL,'$.$key'";
                    $skey1 = "";
                }
                foreach ($vals as $val) {
                    $pf++;
                    $pfields[] = " json_search($this->alias.jdoc$skey1,'one','$val%'$skey2)";
                    $icond .= " $and $ii$i.$value_type like '%$val%' ";
                    $and = " and ";
                }//foreach

                if ($pf > 0)
                    $pcond = " where ";
                for ($pfi = 1; $pfi <= $pf; $pfi += 2) {
                    $pfi2 = $pfi + 1;
                    $pcond .= " substring(" . $pfields[$pfi] . ",1,length(" . $pfields[$pfi] . ")-instr(reverse(" . $pfields[$pfi] . "),'.')) = substring(" . $pfields[$pfi2] . ",1,length(" . $pfields[$pfi2] . ")-instr(reverse(" . $pfields[$pfi2] . "),'.')) and "; //ez mi ez?
                }
                $pcond = substr($pcond, 0, -4);
            } //strpos($val
            else {
                if (strpos($val, '%') !== false)
                    $icond .= " $and $ii$i.$value_type like '$val' ";
                else
                    if ($vtype != "")
                        $icond .= " $and $ii$i.$value_type=cast('$val' as $vtype) ";
                    else
                        $icond .= " $and $ii$i.$value_type='$val'  ";

            }

        return $icond;
    }

    public function useIndex($key, $val, $rdtalias = "")
    {
        if ($this->indexExists($key)) {
            $key_type = "key_" . $this->getResourceTypeFromIndex($key);
            $i = $this->indexes[$key];
            $ii = $rdtalias . "i";
            $icondi = $this->insertConditionToIndex($key, $val, $i, $ii);
            $iand = trim($icondi) === "" ? "" : " and ";
            $index = " join " . $this->t_resource . "._indexes $ii$i use index($key_type)  on ( $ii$i.index_field=$i $iand $icondi and " . $rdtalias . ".id=$ii$i.resource_id ) ";
        }
        return $index;
    }

    public function isConditionUsedInIndex($key, $val)
    {
        return isset($this->conditionsUsedInIndex["$key"]["$val"]);
    }

    function getOperand($key, $val, $and)
    {
        $oper = substr($val, 0, 2);
        if (array_key_exists($oper, $this->cond_prefix)) {
            if (is_nan($val))
                $val = "'" . str_replace("'", "", $val) . "'";
            if ($oper == 'ap') {
                $val = substr($val, 2);
                $ocond = " between " . ($val * $this->app_low) . " and " . ($val * $this->app_high);
            } else
                if ($oper == 'bt') {
                    $val = substr($val, 2);
                    $vals = explode('|', $val);
                    if (count($vals) != 2) {
                        return array('error' => 'Invalid bt operation');
                    }
                    $ocond = " between '" . $vals[0] . "' and '" . $vals[1] . "' ";
                } else {
                    if (strpos($val, '%') !== false)
                        $ocond = " like '$val' ";
                    else
                        $ocond = $this->cond_prefix[$oper] . "'" . substr($val, 2) . "'";
                }

            if ($oper == 'ne') {
                $cond .= " $and json_search(" . $this->alias . ".jdoc,'one','" . substr($val, 2) . "',NULL,'$." . $key . "') is null ";
            } else
                $cond .= " $and " . $this->alias . ".jdoc->>'$.$key' $ocond";

        } else
            if (strpos($val, '|') !== false)  //ezt ha esetleg bekommentezném, hogy ez mi a rák,  köszönöm... /**  ha a keresett érték érték1|érték2|.. formában van, akkor and-el keresi pl. code.coding[*]=http://address|BNO, akkor csak az adott kódrendszer szerinti kódokat */ {
                $vals = explode('|', $val);
        $pf = 0;
        if (strpos($key, '*') !== false) {
            $skey1 = "->>'$.$key'";
            $skey2 = "";
        } else {
            $skey2 = ",NULL,'$.$key'";
            $skey1 = "";
        }
        foreach ($vals as $val) {
            $pf++;
            $pfields .= " ,json_search(" . $this->alias . ".jdoc$skey1,'one','$val%'$skey2) p$pf ";
            $pfieldsa["p$pf"] = " json_search(" . $this->alias . ".jdoc$skey1,'one','$val%'$skey2) ";
            $cond .= " $and json_search(" . $this->alias . ".jdoc$skey1,'one','$val%'$skey2) is not null ";
            $and = " and ";
        }

        $pcond = " and ";

        for ($pfi = 1; $pfi <= $pf; $pfi += 2) {
            $pfi2 = $pfi + 1;
            $p1 = $pfieldsa["p$pfi"];
            $p2 = $pfieldsa["p$pfi2"];
            $pcond .= " substring($p1,1,length($p1)-instr(reverse($p1),'.')) = substring($p2,1,length($p2)-instr(reverse($p2),'.')) and "; // ez tulképp egy json tomb, ez figyeli , hogy a tomb azonos elemei legyenek a találatban, ne pl code[0].coding= és code[1].code
        }
        $pcond = substr($pcond, 0, -4);
    } else
{
if (strpos($key, '*') !== false)
{
$skey1 = "->>'$.$key'";
$skey2 = "";
}

else {
    $skey2 = ",NULL,'$.$key'";
    $skey1 = "";
}

$cond .= " $and json_search(" . $this->alias . ".jdoc$skey1,'one','$val%'$skey2) is not null ";
}

if ($val == 'null')
    $cond = " $and json_contains_path(" . $this->alias . ".jdoc,'one','$.$key') = 0";

return array('cond' => $cond, 'pcond' => $pcond, 'field' => "$alias.jdoc->>'$.$key'", 'pfields' => $pfields);
}



function getReferencedType($field, $ref_resource = "")
{
    $basedb = $this->basedb;
    if ($ref_resource == "")
        $this->db->db_exec("select ref_resource_type as id from " . $this->t_resource . "._reference_types where field='$field'");
    else {
        $this->db->db_exec("select id from $basedb._resources where resource='$ref_resource'");
        $rs = $this->db->db_fetch();
        if ($rs['ID'] != '')
            $this->db->db_exec("select ref_resource_type as id from _" . $ref_resource . "._reference_types where field='$field'");
        else
            return "";
    }
    $rs = $this->db->db_fetch();
    return $rs["ID"];
}


function getReference($key)
{
    //eg.: condition.subject.generalPractitioner.gender -> return Patient

    $kk = explode('.', $key);
    $res = $resource;
    $field = end($kk);
    for ($i = 0; $i < count($kk); $i++) {
        $ref = $kk[$i];

        $rft = $this->getReferencedType($ref);
        if (isset($rft)) {
            $res = $rft;
            $ret[] = $rft;
        } else
            break;
    }
    return $ret;
}

public
function isReference($key)
{
    //eg.: condition.subject is a reference to a Patient

    return is_array($this->getReference($key));
}


private
function getReferenceUsed($refid)
{
    return $this->usedReferences[$refid];
}

private
function isReferenceUsed($refid)
{
    return array_key_exists($refid, $this->usedReferences);
}

private
function setReferenceUsed($refid, $refalias)
{
    $this->usedReferences[$refid] = $refalias;
    return true;
}


public
function useReference($key, $val)
{
    $refs = $this->getReference($key);
    $oval = $val;
    $arr = $this->getField($key);
    $field = $arr['field'];
    $fieldResourceType = $arr['resourceType'];

    $i = 0;
    $reftable = $this->reftable;
    $datatable = $this->alias;
    $datafield = "id";

    $refs2 = $refs;
    $reference .= $reftype;
    $refid = $refs[0];

    $dataalias = "_data" . $this->noOfReferences['data'];
    $rdtalias = "_rdt" . $this->noOfReferences['refdata'];


    if (!$this->isReferenceUsed($refid)) {
        $refalias = "_ref" . $this->noOfReferences['ref'];
        $reference .= "\r\n join $reftable $refalias on ($refalias.ref_resource=$refid $refcond and $refalias.resource_id=$dataalias.$datafield ) ";
        $this->setReferenceUsed($refid, $refalias);
        $oldref = false;
    } else {
        $refalias = $this->getReferenceUsed($refid);
        $oldref = true;
    }
    $this->noOfReferences['ref']++;

    $p_table = $reftable;
    $p_alias = $refalias;
    $p_field = "ref_id";



    $cnt = count($refs) - 1;

    array_shift($refs2);
    array_pop($refs);

    $join = "straight_join";

    $i = 0;
    $rrar = array();
    foreach ($refs as $refid) {

        $reftype = "($i chain)";
        $ref_resource = $this->getResourceName($refid);

        $ref_table = '_' . $ref_resource;

        $refalias = "_reftable" . $this->noOfReferences['ref'];
        $dataalias = "_datatable" . $this->noOfReferences['data'];

        $refdatatable = "_" . $ref_resource . "._data";
        $reftable = "_" . $this->getResourceName($refid) . "._references";

        $refcond = "and $refalias.ref_id='" . str_replace($ref_resource . "/", "", $val) . "'";
        $refcond = "";
        //   $reference   .= "\r\n($reftype)";
        $refid2 = $refs2[$i];
        $reference .= "\r\n join $reftable $refalias on ( $refalias.resource_id=$p_alias.$p_field $refcond and $refalias.ref_resource=$refid2 ) ";
        $p_table = $reftable;
        $p_field = "ref_id";
        $this->noOfReferences['ref']++;
        $rrarkeys = array_keys($rrar);

        if (!in_array($ref_resource, $rrarkeys))
            $rrar[$ref_resource] = 0;
        else
            $rrar[$ref_resource]++;
        $i++;
    }



    $ref_resource = $this->getResourceName($fieldResourceType);

    $rrarkeys = array_keys($rrar);

    if (!in_array($ref_resource, $rrarkeys))
        $rrar[$ref_resource] = 0;


    $reftype = "($i conditional)";
    $refdatatable = "_" . $ref_resource . "._data";

    $rdtalias = "_" . $ref_resource . "_data" . $rrar[$ref_resource];    //2021.02.12 change for _has requests

    $k2 = $this->getField($key)['field'];
    $rr = new _resource($ref_resource);
    if (!$oldref && $rr->indexExists($k2)) {
        $index = $rr->useJoinIndex($k2, $val, $rdtalias);
        $this->conditionsUsedInIndex[$key][$val] = true;
        $join = "join";
    }

    if ($field == "reference") { //our very special case
        $field = "id";
        $val = str_replace($ref_resource . "/", "", $val);
        $refcond = "and $rdtalias.id='$val'";
        $join = "join";
    } else
        if (!$this->isConditionUsedInIndex($key, $val))
            $refcond = "and json_search($rdtalias.jdoc,'one','$val%',null,'$.$field') is not null";

    $reference .= "\r\njoin $refdatatable $rdtalias on (true $refcond and $refalias.ref_id=$rdtalias.id ) $index";

    $this->dts["$refdatatable"][] = $rdtalias;
    $this->noOfReferences['refdata']++;

    if ($reference != "")
        $this->conditionsUsedInReference["$key"]["$oval"] = true;
    return array('sql' => $reference, 'join' => $join, 'datatable' => $refdatatable);
}

public
function isConditionUsedInReference($key, $val)
{
    return isset($this->conditionsUsedInReference["$key"]["$val"]);
}



public
function getField($key)
{
//eg.: condition->subject.generalPractitioner.gender=female ->gender
//antieg period.start -> period.start

    $kks = explode('.', $key);
    $field = "";
    $resourceType = $this->resource;
    $rftp = $this->getResourceId($resourceType);
    foreach ($kks as $kk) {
        $ref = $kk;
        $rft = $this->getReferencedType($ref, $resourceType);

        if ($rft == "") {
            $field .= $ref . ".";
        } else {
            $res = ucfirst($ref);
            $resourceType = $this->getResourceName($rft);
            $rftp = $rft;
        }
    }

    if ($field == "")
        $field = ".";
    $field = substr($field, 0, -1);

    return array('resourceType' => $rftp, 'field' => $field);
}

} //class _resource


/************************************************************************/
/*************************************MAIN*******************************/
/************************************************************************/

$base_resource = new _resource($resource);

$query = $_vars['_query'][0];
if ($query != "") {
    require "$named_queries_dir/$query.php";
    goto get_end;
}


$isSummary = isset($_REQUEST['_summary']);

/*** get the ID, if any */
if ($id != "") {

    switch (substr($id, 0, 2)) {
        case 'eq':
            $oper = ">";
            $id = substr($id, 2);
            break;
        case 'ne':
            $oper = "!=";
            $id = substr($id, 2);
            break;
        case 'gt':
            $oper = ">";
            $id = substr($id, 2);
            break;
        case 'ge':
            $oper = ">=";
            $id = substr($id, 2);
            break;
        case 'lt':
            $oper = "<";
            $id = substr($id, 2);
            break;
        case 'le':
            $oper = "<=";
            $id = substr($id, 2);
            break;
        default:
            $oper = "=";
    }

    if (strpos($id, '%') !== false)
        $cond = "_data0.id like '$id' ";
    else
        $cond = "_data0.id $oper '$id' ";
    $and = " and ";

}

/*** limit no. of returned rows, or no limit if _count=all */
if (strtolower($_vars['_count'][0]) != 'all') {
    $limit0 = $_vars['_count'][0] != "" ? $_vars['_count'][0] : $deflimit;
    if (isset($_vars['_offset'][0]) && isset($limit0)) {
        $limit0 = $_vars['_offset'][0] . "," . $limit0;
    }
    $limit = " limit  $limit0";
}

/*** process sort parameters */
if ($_vars['_sort'][0] != "") {
    $orderby = " order by ";

    $orderkeys = explode(',', $_vars['_sort'][0]);
    $oc = 0;
    foreach ($orderkeys as $okey) {
        $desc = "";
        if (substr($okey, 0, 1) == '-') {
            $okey = substr($okey, 1);
            $desc = " desc";
        }
        $orderby = $oc++ == 0 ? $orderby : $orderby . ",";
        $orderby .= 'jdoc->>"$.' . $okey . '"' . $desc;
    }
}


$i = 0;
$ti = 0;
$index = "";
$indexs = array();
foreach ($_vars as $key => $vals) {
    if (!in_array($key, $keywords)) {
        $aa = explode(":", $key); //search modifiers
        $fields[] = $aa[0];
    }
}
$rresources = $base_resource->collectReferencedResources($fields);


//****************************** convert array param fields to 'field[*]' format *******************//
foreach ($_vars as $key => $vals) {
    if (!in_array($key, $keywords)) {
        {
            $fs = explode(".", $key);
            $cc = false;
            $cfields = "";
            $res0 = $resource;
            foreach ($fs as $field) {
                $astrx = "";
                $cfields .= $field;

                $refres = getRefResourceTypeByField($res0, $field, $db, $basedb);
                if (isset($refres))
                    $res0 = $refres;

                if (!$cc) {
                    $card = getFHIRCardinality($res0, $cfields);
                    if ($card['type'] != 'Reference') {
                        $astrx = is_numeric($card['max']) && $card['max'] < 2 ? "" : "[*]";
                        if ($card['type'][0]['code'] == 'CodeableConcept') {
                            $cc = true;
                        }
                    }
                } //type reference
                $key2 .= $field . $astrx . ".";
                $cfields .= ".";

            }
            if ($cc)
                $key2 = str_replace("code.coding.code", "code.coding[*].code", $key2);

            $key = substr($key2, 0, -1);
            $key2 = "";

        }


//****************************** main part*******************//

        foreach ($vals as $val) {
            if (in_array($key, $keywords)) {
                $fs = explode(".", $val);
                $cc = false;
                $res0 = $resource;
                foreach ($fs as $field) {
                    $astrx = "";

                    $refres = getRefResourceTypeByField($res0, $field, $db, $basedb);
                    if (isset($refres))
                        $res0 = $refres;

                    if (!$cc) {
                        $card = getFHIRCardinality($res0, $field);

                        if ($card['type'] != 'Reference') {
                            $astrx = is_numeric($card['max']) && $card['max'] < 2 ? "" : "[*]";
                            if ($card['type'][0]['code'] == 'CodeableConcept') {
                                $cc = true;
                            }
                        }
                    } //type reference
                    $val2 .= $field . $astrx . ".";
                }
                if ($cc)
                    $val2 = str_replace("code.coding.code", "code.coding[*].code", $val2);
                $val = substr($val2, 0, -1);
                $val2 = "";
            }


            $ti++;
            $ri = 0;
            /***indexes */
            if ($base_resource->indexExists($key)) {
                $index .= $index == "" ? "" : " and ";
                $jindex .= $base_resource->useIndex($key, $val, "_data0");
            }
            /*** process references and condition paramteters ***/

            /***references */
            if ($base_resource->isReference($key)) {
                $references[] = $base_resource->useReference($key, $val);
            }

            /***base conditions ***/
            if (!$base_resource->isConditionUsedInIndex($key, $val) && !$base_resource->isConditionUsedInReference($key, $val)) {

                $aa = explode(":", $key); //search modifiers
                if ($aa[1] == "missing") {
                    $not = $val == "true" ? "" : "not";
                    $cond .= ' jdoc->"$.' . $aa[0] . '"' . " is $not null $and";
                } else {
                    $operands = $base_resource->getOperand($key, $val, $and);
                    $cond .= $operands['cond'];
                    $pcond .= $operands['pcond'];
                    $pfields .= $operands['pfields'];
                }
                $and = " and ";
            }
        }
    } //in_array
} //foreach


$reference = "";
$sjt = $base_resource->getStraightJoin(array_keys($rresources));

foreach ($references as $ref) {
    $join = $sjt == $ref['datatable'] ? 'straight_join' : $ref['join'];
    $reference .= str_replace('__join__', $join, $ref['sql']);
}

$where = " where true";

$tcond = "";
foreach ($base_resource->dts as $table => $trefs) {
    $i = 0;
    foreach ($trefs as $tref) {
        if ($i++ > 0) {
            $t0 = $trefs[0];
            $tcond .= " $t0.id=$tref.id and";
        }
    }
}
if ($tcond != "") {
    $tcond = substr($tcond, 0, -3);
}

if ($cond != "")
    $where .= " and " . $cond;

if ($index != "")
    $where .= " and " . $index;

if ($tcond != "")
    $where .= " and " . $tcond;


/************* construct final SQL  ******************************/

if (!$_hasParent)
    $sql = " select _data0.* $pfields from $t_resource._data _data0 $reference $jindex $where  $condorder "; //$limit ";
else
    $sql = " select _" . str_replace("/", "", $_hasParent) . "_data0.* $pfields from $t_resource._data _data0 $reference $jindex $where  $condorder "; //$limit ";

$deforderby = $base_resource->getOrderBy($sql);
if ($deforderby == "")
    $deforderby = "_data0";
$condorder = $condorder == "" ? "" : " order by " . $condorder . ",$orderTable"; //order by 1

if ($orderby != "") {
    $condorder = $orderby;
}


if ($_REQUEST['_summary'] == 'count') {
    $groupby = "jdoc->>'$." . $_vars['_groupby'][0] . "'";
    $groupby = "trim(concat('',";
    add_debug("1", $_vars);

    foreach ($_vars['_groupby'] as $gb) {
        $groupby .= "jdoc->>'$.$gb',' ',";
//     $groupby="'$.$gb'";
        add_debug("$gb", $groupby);

    }
    $groupby = substr($groupby, 0, -1) . "))";

    if (isset($_vars['_top'][0])) {
        $ascdesc = 'desc';
        $limit = 'limit ' . $_vars['_top'][0];
    }
    if (isset($_vars['_bottom'][0])) {
        $ascdesc = 'asc';
        $limit = 'limit ' . $_vars['_bottom'][0];
    }

    if (isset($groupby)) {
        // $q = " select JSON_OBJECT('resourceType','summaryDetail','value', value, 'count', count) as jdoc from   (select json_extract(jdoc,$groupby) as value,count(0) as count from ( select _data0.* $pfields from $t_resource._data _data0 $reference $jindex $where $pcond $condorder ) a group by 1  order by 2 $ascdesc ) b  $limit";
        $q = " select JSON_OBJECT('resourceType','summaryDetail','value', value, 'count', count) as jdoc from   (select $groupby as value,count(0) as count from ( select _data0.* $pfields from $t_resource._data _data0 $reference $jindex $where $pcond $condorder ) a group by 1  order by 2 $ascdesc ) b  $limit";
        $isSummary = false;
    } else {
        $limit = "";
        $q = " select count(0) as jdoc from ( select _data0.* $pfields from $t_resource._data _data0 $reference  $jindex $where $pcond $condorder $limit) a ";
    }
} else //not count
{
    if (!$_hasParent)
        $q = " select _data0.* $pfields from $t_resource._data _data0 $reference  $jindex $where $pcond $condorder $limit ";
    else
        $q = " select distinct _" . str_replace("/", "", $_hasParent) . "_data0.* $pfields from $t_resource._data _data0 $reference  $jindex $where $pcond $condorder $limit ";
}


if ($_vars['_random'][0] == 1) {
    $db3 = new db_connect($dbparams);
    $db3->db_exec("select table_rows from information_schema.tables where table_schema='$t_resource' and table_name='_data'");
    $crs = $db3->db_fetch();
    $crstart = rand(0, max($crs['TABLE_ROWS'], $crs['TABLE_ROWS'] - 1000));
//    $rlimit = " limit 1000 offset $crstart";
    $q = " select * from (select _data0.* $pfields from $t_resource._data _data0 $reference  $jindex $where $pcond $condorder $rlimit) rr order by rand()  $limit ";
//    $q = " select * from (select _data0.* $pfields from $t_resource._data _data0 $reference  $jindex $where $pcond $condorder) rr order by rand() $limit ";
}

$q = str_replace("where true   ) ", " ) ", $q);


add_debug("query", $q);


if ($debug == 1) { //&& CANDEBUG  )
    echo "1: $_hasParent  " . $q . "\r\n";
    if (isset($groupby)) {
        $limit = "";
        $q = " select count(0) as jdoc from ( select _data0.* $pfields from $t_resource._data _data0 $reference  $jindex $where $pcond $condorder $limit) a ";
        echo "2: " . $q . "\r\n";
    }
} else {

    $db3 = new db_connect($dbparams);
    $db3->db_exec($q);


    $cnt = 0;
    for (; $rs = $db3->db_fetch();) {
//     $res[] = json_decode(utf8_encode($rs['JDOC']),true);
        $res[] = json_decode($rs['JDOC'], true);
    }

    if (isset($groupby)) {
        $limit = "";
        $q = " select count(0) as jdoc from ( select _data0.* $pfields from $t_resource._data _data0 $reference  $jindex $where $pcond $condorder $limit) a ";
        $db3->db_exec($q);
        $rs = $db3->db_fetch();
        $rbundle['total'] = json_decode($rs['JDOC'], true);
    }
    $db3->db_close();
}


function includeReferenced($db, $basedb, $resource, $field, $val, $rinc)
{
    if ($val['reference'] != '') {

        $refres = getRefResourceTypeByField($resource, $field, $db, $basedb);

        $refid = str_replace('urn:uuid:', '', $val['reference']);
        $refid = str_replace($refres . '/', '', $refid);
        if ($refres != "")
            $rinc[] = json_decode(getResourceByID($refres, $refid, $db), true);
    } else {
        foreach ($val as $vkey => $varr) {
            $xrinc = array();
            $xrinc = includeReferenced($db, $basedb, $resource, $field, $varr, $xrinc);

            if (isset($xrinc[0]['resourceType']))
                $rinc[] = $xrinc[0];
        }
    }
    return $rinc;
}


/***************************************************************
 * include resources
 ***************************************************************/
if ($_vars['_include'] != '' && $_vars['_summary'] == '') {

    foreach ($res as $xres) {
        $rinc = array();
        foreach ($xres as $field => $val) {
            if (is_array($val))
                $rinc = includeReferenced($db, $basedb, $resource, $field, $val, $rinc);
        }
    } //foreach as resource
    $res = array_merge($rinc, $res);
} //if


/***************************************************************
 * revinclude resources
 ***************************************************************/
if ($_vars['_revinclude'] != '' && $_vars['_summary'] == '') {
    $rinc = array();

    foreach ($res as $xres)
        $refrs = getAllReferencingResources($resource, $xres['id'], $db, $basedb);

    foreach ($refrs as $rrs)
        $rinc[] = json_decode(getResourceByID($rrs['RESOURCE'], $rrs['RESOURCE_ID'], $db), true);
    $res = array_merge($rinc, $res);
} //if


/*** compose return string */
get_end:


$isBundle = count($res) > 1 || $isSummary;

if ($isBundle) {
    $rbundle['resourceType'] = 'Bundle';
    if (!isset($rbundle['type']))
        $rbundle['type'] = "searchset";
    if ($isSummary)
        $rbundle['total'] = $res[0];
    else {
        foreach ($res as $key => $rs) {
            $tagged = false;
            if (is_array($rs['meta']['tag'])) {
                foreach ($rs['meta']['tag'] as $tag)
                    if ($tag['system'] == $fhr_system && 'code' == $fhr_system_code) {
                        $tagged = true;
                        break;
                    }
            }
            if (!$tagged && $_vars['_query'][0] == "")
                $rs['meta']['tag'][] = array('system' => $fhr_system, 'code' => $fhr_system_code);


            if (isset($rs['meta']['profile']) && strpos($rs['meta']['profile'], 'http') === false)
                unset($rs['meta']['profile']);
            if (isset($rs['resourceType'])) {

                $rs['meta']['versionId'] = isset($rs['meta']['versionId']) ? '' . $rs['meta']['versionId'] : "1";
                $rbundle['entry'][$key]['resource'] = $rs;
                $rbundle['entry'][$key]['fullUrl'] = $baseurl . "/" . $rs['resourceType'] . "/" . $rs['id'];
                if ($rbundle['type'] == 'transaction') {
                    $rbundle['entry'][$key]['request'] = array('method' => 'PUT', 'url' => $baseurl . "/" . $rs['resourceType'] . "/" . $rs['id']);
                }
            } else
                $rbundle['entry'][$key] = $rs;


        } //foreach
    } //else
    $result['status'] = '200';
    $result['body'] = $rbundle;
}  //isBundle
else
    if (count($res) == 1) {
        if (isset($res[0]['meta']['profile']) && strpos($res[0]['meta']['profile'], 'http') === false)
            unset($res[0]['meta']['profile']);

        if (is_array($res[0]['meta']['tag'])) {
            foreach ($res[0]['meta']['tag'] as $tag)
                if ($tag['system'] == $fhr_system && 'code' == $fhr_system_code) {
                    $tagged = true;
                    break;
                }
        }
        if (!$tagged && $_vars['_query'][0] == "")
            $res[0]['meta']['tag'][] = array('system' => $fhr_system, 'code' => $fhr_system_code);


        $res[0]['meta']['versionId'] = '' . $res[0]['meta']['versionId'];

        if (!isset($res[0]['resourceType']))
            $res[0]['resourceType'] = $resource;
        $result['status'] = '200';
        $result['body'] = $res[0];
    } else {
        $result['status'] = '200';
        $result['body'] = array('Not found');
    }

if (count($debugs) > 0 && $debug == 1)
    $result['body']['debug'][] = $debugs;

?>