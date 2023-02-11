<?php
/**************************************************************
 * create database schema for FHIR Resource
 **************************************************************/

/******************************/
function crdb($resource)
{
    /******************************/
    global $db, $dbroot, $dbrpass;
    global $basedb;


    $dbparams['user'] = $dbroot;
    $dbparams['pass'] = $dbrpass;
    $db = new db_connect($dbparams);

    $t_resource = "_" . $resource;

    $maxtime = 10; //seconds
    $start = time();
    do {
        $db->db_exec("select * from $basedb._resources where resource='$resource'");
        $rs = $db->db_fetch();
        $timeout = time() - $start > $maxtime;
        if ($rs['LOCKED'] == 1)
            usleep(500000);
    } while (($rs['RESOURCE'] == '$resource' && $rs['LOCKED'] == 1) || $timeout);

    if ($timeout || !isset($rs['RESOURCE'])) {
        $db->db_exec("insert ignore into $basedb._resources (resource) values ('$resource')");
        $cr_db = "create database if not exists $t_resource";

        /******************************/
        $cr_base = "
CREATE TABLE if not exists $basedb._resources
(
 id       integer NOT NULL auto_increment primary key,
 resource varchar(255) NOT NULL ,
 rowcount bigint not null default 0,
 unique key i_res1 (resources) 
)  ENGINE=INNODB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPRESSED COMMENT='Resource list'";


        /******************************/
        $cr_json = "
CREATE TABLE if not exists $t_resource._json_sequence
(
 id integer unsigned  NOT NULL auto_increment,
PRIMARY KEY (id)
)  ENGINE=INNODB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPRESSED COMMENT='Sequence for IDs'";

        /******************************/
        $cr_data = "
CREATE TABLE if not exists $t_resource._data
(
 id      varchar(255) NOT NULL ,
 JDOC    json NOT NULL ,
 version integer unsigned NOT NULL default 1,
PRIMARY KEY (id)
) 
 ENGINE=INNODB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPRESSED 
COMMENT='Base datastore for resource'
";

        /******************************/
        $cr_data_history = "
CREATE TABLE if not exists $t_resource._data_history
(
 id      varchar(255) NOT NULL ,
 JDOC    json NOT NULL ,
 version integer unsigned NOT NULL default 1,
PRIMARY KEY (id,version)
) 
 ENGINE=INNODB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPRESSED 
COMMENT='Base datastore for resource'
";

        /******************************/
        $cr_indexed_fields = "
CREATE TABLE if not exists $t_resource._indexed_fields
(
 id       integer unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
 field    varchar(255) NOT NULL ,
 datatype varchar(255) NOT NULL ,
 locked   tinyint(1) not null default 1
) ENGINE=INNODB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPRESSED COMMENT='Index list'";

        /******************************/
        $cr_indexes = "
CREATE TABLE if not exists $t_resource._indexes
(
 index_field    integer unsigned NOT NULL ,
 resource_id    varchar(255) NOT NULL ,
 array_id integer unsigned not null default 0,
 value_string   varchar(255) NULL ,
 value_integer  integer NULL ,
 value_double   double NULL ,
 value_datetime datetime NULL ,
PRIMARY KEY (index_field, resource_id,array_id),
KEY key_resid (index_field, resource_id),
KEY key_datetime (index_field, value_datetime, resource_id),
KEY key_double (index_field, value_double, resource_id),
KEY key_integer (index_field, value_double, resource_id),
KEY key_string (index_field, value_string, resource_id)
) ENGINE=INNODB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPRESSED COMMENT='Index store for data.JDOC JSON elements '
partition by list columns(index_field)
(partition r0 values in (0)) 
";

        /******************************/
        $cr_reference_types = "
CREATE TABLE if not exists $t_resource._reference_types
(
 ref_resource_type integer unsigned NOT NULL ,
 field             varchar(255) NOT NULL ,
PRIMARY KEY (ref_resource_type, field)
) 
 ENGINE=INNODB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPRESSED 
COMMENT='Resource type of referenced resources from field: data.JDOC element'";

        /******************************/
        $cr_references = "
CREATE TABLE if not exists $t_resource._references
(
 id integer unsigned not null auto_increment,
 ref_resource integer unsigned NOT NULL ,
 resource_id  varchar(255) NOT NULL ,
 ref_id       varchar(255) NOT NULL ,
PRIMARY KEY (id, ref_resource ),
unique KEY key_ref (ref_resource,resource_id,ref_id ),
key ref_resource (ref_resource,ref_id)
) 
ENGINE=INNODB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPRESSED COMMENT='Resources referenced by this resource' 
partition by list columns(ref_resource)
(partition r0 values in (0)) 
";

        $log = "$resource\r\n";
        file_put_contents($LOGS . "/crdb.log", $log, FILE_APPEND);

        $db->db_exec($cr_db);
        $db->db_exec($cr_base);
        $db->db_exec($cr_json);
        $db->db_exec($cr_data);
        $db->db_exec($cr_data_history);
        $db->db_exec($cr_indexed_fields);
        $db->db_exec($cr_indexes);
        $db->db_exec($cr_reference_types);
        $db->db_exec($cr_references);

        /***************grant privileges *********************/


        $server = MASTER_DB_SERVER;
        $q = "grant all privileges on $t_resource.* to '$user'@'$server'";

        foreach ($backup_db_servers as $server) {
            $q = "grant select on $t_resource.* to '$user'@'$server'";
            $db->db_exec($q);
        }

        $db->db_exec("update $basedb._resources set locked=false where resource='$resource'");


    } //timeout
}


?>