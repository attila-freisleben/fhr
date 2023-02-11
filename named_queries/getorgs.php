<?php

$distance = isset($_vars['distance'][0]) ? $_vars['distance'][0] : 10;
$city = isset($_vars['address.city'][0]) ? $_vars['address.city'][0] : "";
$nat = $_vars['address.country'][0];
$lang = $_vars['lang'][0];
$state = isset($_vars['address.state'][0]) ? $_vars['address.state'][0] : "";
$district = isset($_vars['address.district'][0]) ? $_vars['address.district'][0] : "";

$minpop = isset($_vars['minpop'][0]) ? $_vars['minpop'][0] : 10000;
$maxpop = 100000;

if (!isset($lang))
    $lang = $nat;

$oc['NL'] = array('Poliekliniek', 'Ambulante zorg', 'Kliniek', 'Ziekenhuis', 'Hospitaal', 'Gasthuis');
$oc['EN'] = array('Clinic', 'Outpatient clinic', 'Policlinic');
$oc['GB'] = array('Clinic', 'Outpatient clinic', 'Policlinic');
$oc['HU'] = array('Szakrendelő', 'Rendelőintézet');
$oc['DE'] = array('Klinik', 'Krankenhaus', 'Spital', 'Poliklinik', 'Lazarett', 'Krankenanstalt');
$oc['AT'] = $oc['DE'];
$oc['FI'] = array('Poliklinikka', 'Klinikka', 'Sairaala');
$oc['EE'] = array('Polikliinik', 'Kliinik', 'Haigla');
$oc['UA'] = array('клініка', 'лікарня', 'поліклініка');


if (!isset($oc[$nat]))
    $oc[$nat] = $oc['EN'];

if ($state != "") {
    $db->db_exec("select * from $rangeDB.admin1codes where code like '$nat.%' and name='$state'");
    $rs = $db->db_fetch();
    $prefix = $rs['CODE'];
    $wd = " and city.admin1_code='" . $prefix . "'";
    $wd = str_replace($nat . ".", "", $wd);
    $a1code = $prefix;
}


if ($district != "") {
    $db->db_exec("select * from $rangeDB.admin2codes where code like '$nat.%' and name='$district'");
    $rs = $db->db_fetch();
    $prefix = $rs['CODE'];
    $cx = explode(".", $prefix);
    $xa1code = $cx[1];
    $xa2code = $cx[2];
    $wd = " and city.admin1_code='$xa1code' and city.admin2_code='$xa2code'";
    $a2code = $prefix;
}


$q = "
 select * from (
 SELECT city.id as id,city.name as city_name,city.admin2_code,city.population as city_population,city.latitude as city_latitude,city.longitude as city_longitude,round(acos(sin(radians(org.latitude)) * sin(radians(city.latitude)) + cos(radians(org.latitude)) * cos(radians(city.latitude)) * cos(radians(city.longitude) - (radians(org.longitude)))) * 6371) as dist,   
 rank() over (partition by city.name order by round((acos(sin(radians(org.latitude)) * sin(radians(city.latitude)) + cos(radians(org.latitude)) * cos(radians(city.latitude)) * cos(radians(city.longitude) - (radians(org.longitude)))) * 6371),5),city.feature_class) as rnk,
 org.name as org_city,org.latitude as org_latitude,org.longitude as org_longitude,concat(org.country_code,'.',org.admin1_code) as org_admin1_code,concat(org.country_code,'.',org.admin1_code,'.',org.admin2_code) as org_admin2_code FROM fhiringRange.geonames city,
  ( select name,latitude,longitude,admin1_code,admin2_code,country_code from fhiringRange.geonames where country_code='$nat' and feature_class='A' and feature_code='ADM3' and population>=$minpop
     union 
     select name,latitude,longitude,admin1_code,admin2_code,country_code from fhiringRange.geonames where country_code='$nat' and feature_class='P' and population>=$minpop
) org where city.country_code='$nat' and (city.feature_class='P' or city.feature_code='ADM3')  $wd 
 ) a where rnk=1 order by org_latitude,org_longitude";


$db->db_exec($q);

for (; $rs = $db->db_fetch();) {
    $cities[] = $rs;

    if (!isset($orgs[$rs['ORG_CITY']]['population'])) {
        $orgs[$rs['ORG_CITY']]['population'] = 0;
    }
    $cityorg[$rs['CITY_NAME']][] = tc($rs['ORG_CITY'], $lang);

    $orgs[$rs['ORG_CITY']]['prefix'] = $prefix;
//  $orgs[$rs['ORG_CITY']]['population']+=$rs['CITY_POPULATION'];
    $orgs[$rs['ORG_CITY']]['latitude'] = $rs['ORG_LATITUDE'] * 1;
    $orgs[$rs['ORG_CITY']]['longitude'] = $rs['ORG_LONGITUDE'] * 1;
    $orgs[$rs['ORG_CITY']]['admin1_code'] = $rs['ORG_ADMIN1_CODE'];
    $orgs[$rs['ORG_CITY']]['admin2_code'] = $rs['ORG_ADMIN2_CODE'];
    $orgs[$rs['ORG_CITY']]['citiesX'][] = array('city_name' => tc($rs['CITY_NAME'], $lang), 'population' => $rs['CITY_POPULATION'], 'lat' => $rs['CITY_LATITUDE'] * 1, 'lon' => $rs['CITY_LONGITUDE'] * 1, 'id' => $rs['ID']);
}

$donecity = array();
foreach ($orgs as $org_city => $org) {

    $orgs[$org_city]['population'] = 1;

    if (isset($a1code) && $a1code <> $org['admin1_code']) {
        continue;
    }

    if (isset($a2code) && $a2code <> $org['admin2_code']) {
        continue;
    }


    foreach ($org['citiesX'] as $ocity) {
        if (in_array($ocity['city_name'], $donecity)) {
            continue;
        }
        $orgs[$org_city]['population'] += $ocity['population'];
        $orgs[$org_city]['cities'][] = $ocity;
        $donecity[] = $ocity['city_name'];
    }

    $org['population'] = $orgs[$org_city]['population'];
    $org['cities'] = $orgs[$org_city]['cities'];


    unset($org['citiesX']);

    if ($org['population'] >= $maxpop) {
        $orgtotal = $org['population'];
        $maxi = ceil($org['population'] / $maxpop);
        for ($i = 1; $i <= $maxi; $i++) {

            $d = $i * 8;
            $orgname = tc($org_city . " " . $oc[$nat][rand(0, count($oc[$nat]) - 1)] . " - " . $i, $lang);
            $org['name'] = tc($orgname, $lang);
            $org['country'] = $nat;
            $org['city'] = tc($org_city, $lang);
            $org['latitude'] = $org['latitude'] + rand(-$d, $d) / 1000;
            $org['longitude'] = $org['longitude'] + rand(-$d, $d) / 1000;
            $org['population_total'] = $orgtotal;
            $org['population'] = ceil($orgtotal / $maxi);
            $org2s['organizations'][] = $org;
            $res[] = $org;
        }
    } else {
        $orgname = tc($org_city . " " . $oc[$nat][rand(0, count($oc[$nat]) - 1)], $lang);
        $org['name'] = tc($orgname, $lang);
        $org['city'] = tc($org_city, $lang);
        $org['country'] = $nat;
        $d = 5;
        $org['latitude'] = $org['latitude'] + rand(-$d, $d) / 1000;
        $org['longitude'] = $org['longitude'] + rand(-$d, $d) / 1000;
        $org2s['organizations'][] = $org;
        $res[] = $org;
    }
}
ksort($res);


?>

 