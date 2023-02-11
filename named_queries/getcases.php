<?php

$distance = isset($_vars['distance'][0]) ? $_vars['distance'][0] : 10;
$city = isset($_vars['address.city'][0]) ? $_vars['address.city'][0] : "";
$nat = $_vars['address.country'][0];

$minpop = 20000;
$maxpop = 100000;

$oc['NL'] = 'Ambulante zorg';
$oc['EN'] = 'Outpatient clinic';
$oc['HU'] = 'Szakrendel�';

/*************************************************************************************
 * cases =( case / 10t population / profession  / doy) * population/10t / no.locations
 * doctors = cases / (slots / hour * working hours)
 *
 * create table orv_daily_patient (szakma char(4),orvkod char(5),doy integer,cnt integer) charset=utf8;
 * insert into orv_daily_patient select szakma,orvkod,dayofyear(datum) as doy,count(*) as cnt from alap group by 1,2,3 ;
 **************************************************************************************/
$q = " select szakma,avg(cnt) as cnt from (select  szakma,doy,avg(cnt) cnt from fhiringRange.orv_daily_patient a group by 1,2) b group by 1 order by 1";
$db->db_exec($q);
$avg_daily_patient = array();
for (; $rs = $db->db_fetch();) {
    $avg_daily_patient[$rs['SZAKMA']] = $rs['CNT'];
}


$q = "select  szakma,avg(cptt) avgcptt,max(cptt) maxcptt from (select szakma,evnap,sum(cnt)/1000 cptt from fhiringRange.forg_knds_jb group by szakma,evnap) a group by szakma ";    // /1000 -> 10e lakosra jut� ( / 10 milli� * 10,000)

$db->db_exec($q);
for (; $rs = $db->db_fetch();) {
    $res[] = array('szakma' => $rs['SZAKMA'], 'avg_cases_per_10t_daily' => $rs['AVGCPTT'], 'max_cases_per_10t_daily' => $rs['MAXCPTT'], 'avg_daily_patient_doc' => $avg_daily_patient[$rs['SZAKMA']]);
}
