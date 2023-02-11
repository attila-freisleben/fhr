<?php
$script_start_time = microtime(true);
$rstart = microtime(true);
set_time_limit(180);
error_reporting(E_ERROR);
include_once("../inc/include.php");

$debug = $_REQUEST['_debug'] != "";
$resource = $_REQUEST['resource'];
$method = $_REQUEST['method'];
$t_resource = "_" . $resource;
$t_resource_history = $resource . "_history";
$id = $_REQUEST['id'];
$crf = isset($_REQUEST['_crf']);


$db = new db_connect($dbparams);
$db2 = new db_connect($dbparams);


if (isset($_REQUEST['_c']) && $method == 'GET') {  //cacheable
    $_SERVER['REQUEST_URI'] = str_replace("&_crf=1", "", $_SERVER['REQUEST_URI']);
    $fname = md5($_SERVER['REQUEST_URI']);
    $db->db_exec("select * from xfhir.cache_log where file='$fname' ");
    $rs = $db->db_fetch();
    if ($rs['FILE'] != '') {
        if (file_exists('cache/' . $fname)) {
            $str = file_get_contents('cache/' . $fname);
            header("Content-Type: application/fhir+json", true, $result['status']);
            echo $str;
            file_put_contents('cache/cache_served.log', date('Y-m-d H:i:s') . " " . $fname . "\n", FILE_APPEND);
            if (!$crf)
                exit;
        } else  //if !file_exists
        {
            header("Content-Type: application/fhir+json", true, 202);
            echo json_encode(array('info' => 'Request in processing queue, please check back later'), JSON_PRETTY_PRINT);
            exit;
        }
    } else { // $rs['FILE'] ==""
        $uri = $_SERVER['REQUEST_URI'];
        $db->db_exec("insert into xfhir.cache_log values ('$fname','$uri',null,null)");
    }
}


logger("$method\t" . $resource . "\t" . urldecode($_SERVER['REQUEST_URI']));


/*** get request body */
$jdoc = file_get_contents('php://input');
if ($jdoc != "") {
    $jsonarr = json_decode(utf8_encode($jdoc), true);
    /*** check JSON syntax */
    if (json_last_error() !== JSON_ERROR_NONE) {
        $result[0]['status'] = '400';
        $result[0]['body'] = json_encode(array(utf8_encode("JSON error: " . json_last_error_msg())));
        goto eleven;
    }
}

/******* _has ************/
$uri = $_SERVER['REQUEST_URI'];

$q = explode('_has', urldecode($uri));
//$q = explode('&',$q[1]);
//print_r($q);
if (count($q) > 1) {
    $ha1 = explode("?_has=", $uri);
    $url1 = $baseurl . "/";
    $url2 = str_replace(':', '?', $ha1[1]);
    $uri = $url1 . $url2;
    $_hr = explode("?", $url2);
    $_hasResource = $_hr[0];

    $_hasParent = str_replace($baseurl . "/", '', $ha1[0]);
    $uristart = strpos($uri, "?") === false ? "?" : "&";

    $uri .= $uristart . "subject.resourceType=" . str_replace("/", "", $_hasParent) . "&resourceType=" . str_replace("/", "", $_hasResource);
    $resource = $_hr[0];
    $t_resource = "_" . $resource;
    $t_resource_history = $resource . "_history";
//     echo "\r\nresource: $resource,  hasParent:$_hasParent, $uri\r\n";  
}

/*** process request params: put relevant FHIR fields into $_vars  */
$q = explode('?', urldecode($uri));
$q = explode('&', $q[1]);

foreach ($q as $line) {
    $p = explode('=', $line);
    if ($p[0] != "" && $p[1] != "")
        $_vars[$p[0]][] = $p[1];
}
//unset($_vars['_debug'][0]);


/*** process Bundle */
$entryno = 1;

if (!waitForResource($resource)) {
    $result['body']['error'] = "Resource busy";
    goto eleven;
}

if ($jsonarr['resourceType'] == 'Bundle') {
    foreach ($jsonarr['entry'] as $entry) {
        $isBundle = true;
        $bundleType = $jsonarr['type'];

        $jdoc = json_encode($entry['resource']);
        $resourceType = $entry['resource']['resourceType'];
        $url = $entry['request']['url'] != '' ? $entry['request']['url'] : "$baseurl/$resourceType";
        $method = 'POST';
        $method = $entry['request']['method'] != '' ? $entry['request']['method'] : $method;
        usleep(rand(500, 2500));
        $result['body']['entry']['resource'][] = curl_send_fhir($url, $method, $jdoc);
        $entryno++;
    }
} else //*** or a single request */
    require(strtoupper($method) . ".php");

eleven:
/*** return values ***/
/*** todo: all */

$rend = microtime(true);
$result['body']['meta']['lastUpdated'] = getTdate();


$lang = $_vars['_lang'][0];
//*************** translate
if (substr($lang, 0, 2) != 'en' && substr($lang, 0, 2) != '') {
    unset($res2);

    if ($result['body']['resourceType'] == 'Bundle') {
        $res = $result['body']['entry'];

        foreach ($res as $key => $rs) {
            if (isset($rs['resource']))
                $res2[] = translateResource($rs['resource'], $lang);
            else
                if (is_numeric($key))
                    $res2[] = $rs;
                else
                    $res2['$key'] = $rs;
        }

        unset($res);
        foreach ($res2 as $rs)
            $res[] = array('resource' => $rs);
        $result['body']['entry'] = $res;
    } else {
        $result['body'] = translateResource($result['body'], $lang);
    }
}


// output result

$str = json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


if ((isset($_REQUEST['_c']) && $method == 'GET')) {  //cacheable
    $fname = md5($_SERVER['REQUEST_URI']);
    $uri = $_SERVER['REQUEST_URI'];
    file_put_contents("cache/" . $fname, $str);

    $script_run_time = microtime(true) - $script_start_time;
    $db->db_exec("update xfhir.cache_log set time=$script_run_time, last_refresh=now() where file='$fname'");
}


header("Content-Type: application/fhir+json", true, $result['status']);
echo $str;


$db2->db_close();
$db->db_close();
?>