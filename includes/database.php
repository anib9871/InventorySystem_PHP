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
  $host = getenv('MYSQLHOST') ?: '127.0.0.1';
  $user = getenv('MYSQLUSER') ?: 'root';
  $pass = getenv('MYSQLPASSWORD') ?: 'Mysql123@';
  $db   = getenv('MYSQLDATABASE') ?: 'inventory_system';
  $port = getenv('MYSQLPORT') ?: '3306';

  $this->con = mysqli_connect($host, $user, $pass, $db, $port);

  if(!$this->con)
  {
    die("Database connection failed: " . mysqli_connect_error());
  }
}
/*--------------------------------------------------------------*/
/* Function for Close database connection
/*--------------------------------------------------------------*/

public function db_disconnect()
{
  if(isset($this->con))
  {
    mysqli_close($this->con);
    unset($this->con);
  }
}
/*--------------------------------------------------------------*/
/* Function for mysqli query
/*--------------------------------------------------------------*/
public function query($sql)
   {

      if (trim($sql != "")) {
          $this->query_id = $this->con->query($sql);
      }
      if (!$this->query_id)
        // only for Develope mode
              die("Error on this Query :<pre> " . $sql ."</pre>");
       // For production mode
        //  die("Error on Query");

       return $this->query_id;

   }

/*--------------------------------------------------------------*/
/* Function for Query Helper
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
 /* Function for Remove escapes special
 /* characters in a string for use in an SQL statement
 /*--------------------------------------------------------------*/
 public function escape($str){
   return $this->con->real_escape_string($str);
 }
/*--------------------------------------------------------------*/
/* Function for while loop
/*--------------------------------------------------------------*/
public function while_loop($loop){
 global $db;
   $results = array();
   while ($result = $this->fetch_array($loop)) {
      $results[] = $result;
   }
 return $results;
}

}

$db = new MySqli_DB();

?>
