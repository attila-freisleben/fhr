<?php


$db->db_exec("select * from $basedb._resources");

for (; $rs = $db->db_fetch();) {
    $rss[$rs['RESOURCE']] = number_format($rs['ROWCOUNT'], 0, ".", ",");
}

$dbinfo = "<table ><tr><th>Resource</th><th>No. of elements</th></tr>";
foreach ($rss as $key => $val)
    $dbinfo .= "<tr><td>$key</td><td align='right'>$val</td></tr>";
$dbinfo .= "/<table>";

$result['body']['text']['status'] = 'generated';
$result['body']['text']["div"] = "<div  xmlns=\"http://www.w3.org/1999/xhtml\">" . $dbinfo . "</div>";
$result['body']['resourceType'] = "Basic";
$result['body']['resources'] = $rss;


?>