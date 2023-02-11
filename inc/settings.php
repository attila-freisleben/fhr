<?php


$hl7fhirBase = "http://hl7.org/fhir/StructureDefinition/";
$baseurl = ""; //base server
$rangebase = ""; //range server
$loincurl = ""; //loinc server

$basedb = 'xfhir';
$rangeDB = 'fhiringRange';

$fhr_system = "";
$fhr_system_code = $fhr_system.".V0.1";

$basedir = '';
$named_queries_dir = 'named_queries';
$logs = $basedir . "logs";

$aa = explode("/", $_SERVER['DOCUMENT_ROOT']);
$app = array_pop($aa);

if ($app == "") {
    $aa = explode("/", $_SERVER['PWD']);
    $app = array_pop($aa);
}

ini_set('error_log', "$logs/" . $app . "_error.log");


define("MASTER_LB_SERVER", "");
define("MASTER_DB_SERVER", "");

$backup_db_servers[] = "";

$dbserver = MASTER_DB_SERVER;

$dbroot = '';
$dbrpass = '';

$dbparams = array('user' => '', 'pass' => '', 'schema' => $basedb, 'type' => 'mysqli', 'server' => $dbserver);


?>