<?php

$distance = isset($_vars['distance'][0]) ? $_vars['distance'][0] : 10;
$city = isset($_vars['address.city'][0]) ? $_vars['address.city'][0] : "";
$nat = $_vars['address.country'][0];


$q = "select szakma,name,snomed,display,sum(cnt)/1000 as tpat from forg_knds_jb a join szakmak b on (a.szakma=b.code and type='O') group by 1,2,3,4 order by 5;";

$db->db_exec($q);

for (; $rs = $db->db_fetch();) {
    $res[] = $s;

}

?>

