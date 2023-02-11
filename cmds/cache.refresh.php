<?php

set_include_path("./inc");
include("include.php");
set_time_limit(0);

$param = $argv[1];

if ($param == "")
    $param = "-q";

$ql = $argv[2];

$nolock = $argv[3];

$fname = 'cache.refresh.lock';

//if(time() > filemtime($fname) + (60*60))
//  unlink($fname);

if (file_exists($fname) && !$nolock) {
    echo "cache refresh is already runing\r\n";
    exit;
}

$str = "cache.refresh started at " . date('Y-m-d H:i:s');
echo "\r\nlockfile:$fname\r\n";
if (!$nolock)
    file_put_contents($fname, $str);

try {

    $db = new db_connect($dbparams);
    $db2 = new db_connect($dbparams);
    if ($param == "-q")
        $db->db_exec("select * from xfhir.cache_log where time<500 order by time");
    else
        if ($param == "-f")
            $db->db_exec("select * from xfhir.cache_log order by time");
        else
            if ($param == "-l")
                $db->db_exec("select * from xfhir.cache_log where uri like '%$ql%' order by time");
            else
                $db->db_exec("select * from xfhir.cache_log where now()>last_refresh + interval (time*5) second order by time");

    for (; $rs = $db->db_fetch();) {
        $log = date('Y-m-d h:i:s') . " " . $rs['FILE'] . " " . $rs['URI'] . " " . $rs['TIME'] . " " . $rs['LAST_REFRESH'] . "\r\n";
        file_put_contents('crf.log', $log, FILE_APPEND);
        $uri = $rs['URI'] . "&_crf=1";
        $dt = round($data['run_time'] * 1.2);
        echo $baseurl . $uri . ":::$dt\r\n";
        try {
            file_get_contents_https($baseurl . $uri, $dt);
        } catch (Exception $e) {
            echo "message: " . $e->getMessage() . "/r/n";
        }


        $log[$key] = array('date' => date('Y-m-d H:i:s'), 'url' => $baseurl . $uri);
        file_put_contents("./REST/cache/refresh.log", json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
} catch (Exception $e) {
    echo $e->getMessage();
}

if (!$nolock)
    unlink($fname);

?>