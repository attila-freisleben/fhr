<?php
//set_time_limit(7200);

class db_connect
{
    var $db_type;
    var $db_conn;
    var $db_resource;
    var $rowcount;

    function db_connect($param1, $password = "", $connectstring = "", $type = "", $server = "")
    {
        /***********************************************************************************/
        if (is_array($param1)) {
            $username = $param1['user'];
            $password = $param1['pass'];
            $connectstring = $param1['schema'];
            $type = $param1['type'];
            $server = $param1['server'];
        } else
            $username = $param1;

        $this->db_conn = new mysqli($server, $username, $password, $connectstring);
        if (!$this->db_conn) {
            echo "Error: Unable to connect to MySQL." . PHP_EOL;
            echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
            echo "Debugging error: " . mysqli_connect_error() . PHP_EOL;
            exit;
        }

        return $this->db_conn;
    }

    function start_transaction()
    {
        $this->db_conn->autocommit(false);
        $this->db_conn->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
    }

    function commit()
    {
        $this->db_conn->commit();
    }


    /***********************************************************************************/

    function db_exec($query, $err = true)
    {
        /***********************************************************************************/
        $this->db_resource = $this->db_conn->query($query);
        $this->rowcount = $this->db_resource->num_rows;
        $ret = mysqli_insert_id($this->db_conn);
        if ($err & !$this->db_resource)
            echo "<BR>$query<BR>\n" . mysqli_error($this->db_conn);
        return $ret;

    }

    /***********************************************************************************/

    function db_fetch($ftype = 0)
    {
        /***********************************************************************************/
        $db_result = $this->db_resource->fetch_array(MYSQLI_ASSOC); //mysqli_fetch_array($this->db_resource, MYSQL_ASSOC);
        if (is_array($db_result))
            $db_result = array_change_key_case($db_result, CASE_UPPER);
        return $db_result;
    }

    /***********************************************************************************/

    function db_seek($rownum)
    {
        /***********************************************************************************/
        $db_result = $this->db_resource->mysqli_data_seek($rownum); ////fetch_array(MYSQLI_ASSOC); //mysqli_fetch_array($this->db_resource, MYSQL_ASSOC);
    }


    /***********************************************************************************/

    function db_close()
    {
        /***********************************************************************************/

        $this->db_conn->rollback();
        mysqli_close($this->db_conn);
    }

    /***********************************************************************************/

    function rollback()
    {
        $this->db_conn->rollback();
    }


}


?>
