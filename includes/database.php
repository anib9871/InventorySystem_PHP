<?php
require_once(LIB_PATH_INC.DS."config.php");

class MySqli_DB {

    private $con;
    public $query_id;

    function __construct() {
      $this->db_connect();
    }

/*--------------------------------------------------------------*/
/* Function for Open database connection
/*--------------------------------------------------------------*/
public function db_connect()
{
    $host = getenv('MYSQLHOST');
    $user = getenv('MYSQLUSER');
    $pass = getenv('MYSQLPASSWORD');
    $port = getenv('MYSQLPORT');

    /* DEFAULT DB (MASTER) */
    $dbname = getenv('MYSQLDATABASE');

    /* 🔥 SWITCH DATABASE (MULTI ORG SUPPORT) */
    if(isset($_SESSION['db_name']) && !empty($_SESSION['db_name'])){
        $dbname = $_SESSION['db_name'];
    }

    /* CONNECT */
    $this->con = mysqli_connect($host, $user, $pass, $dbname, $port);

    /* FALLBACK (IMPORTANT) */
    if(!$this->con){

        $dbname = getenv('MYSQLDATABASE'); // master db

        $this->con = mysqli_connect($host, $user, $pass, $dbname, $port);

        if(!$this->con){
            die("Database connection failed: " . mysqli_connect_error());
        }
    }

    mysqli_set_charset($this->con,"utf8");
}
/*--------------------------------------------------------------*/
/* Close database connection */
/*--------------------------------------------------------------*/

public function db_disconnect()
{
if(isset($this->con)){
mysqli_close($this->con);
unset($this->con);
}
}

/*--------------------------------------------------------------*/
/* Query function */
/*--------------------------------------------------------------*/

public function query($sql)
{

if(trim($sql) != ""){
$this->query_id = $this->con->query($sql);
}

if(!$this->query_id){
die("Error on this Query :<pre>".$sql."</pre>");
}

return $this->query_id;

}

/*--------------------------------------------------------------*/
/* Query helper functions */
/*--------------------------------------------------------------*/

public function fetch_array($statement)
{
return mysqli_fetch_array($statement);
}

public function fetch_object($statement)
{
return mysqli_fetch_object($statement);
}

public function fetch_assoc($statement)
{
return mysqli_fetch_assoc($statement);
}

public function num_rows($statement)
{
return mysqli_num_rows($statement);
}

public function insert_id()
{
return mysqli_insert_id($this->con);
}

public function affected_rows()
{
return mysqli_affected_rows($this->con);
}

/*--------------------------------------------------------------*/
/* Escape string */
/*--------------------------------------------------------------*/

public function escape($str){
return $this->con->real_escape_string($str);
}

/*--------------------------------------------------------------*/
/* While loop helper */
/*--------------------------------------------------------------*/

public function while_loop($loop){

$results = array();

while($result = $this->fetch_array($loop)){
$results[] = $result;
}

return $results;

}

}

$db = new MySqli_DB();

?>
