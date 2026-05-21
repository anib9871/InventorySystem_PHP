<?php
ob_start();

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

class Session {

 public $msg;
 private $user_is_logged_in = false;

 function __construct(){
   $this->flash_msg();
   $this->userLoginSetup();
 }

 public function isUserLoggedIn(){
    return $this->user_is_logged_in;
 }

 public function login($user_id){

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user_id;
 }

 private function userLoginSetup(){

    $this->user_is_logged_in =
        isset($_SESSION['user_id']) &&
        isset($_SESSION['username']);
 }

 public function logout(){

    $_SESSION = array();

    if (ini_get("session.use_cookies")) {

      $params = session_get_cookie_params();

      setcookie(
         session_name(),
         '',
         time() - 42000,
         $params["path"],
         $params["domain"],
         $params["secure"],
         $params["httponly"]
      );
    }

    session_destroy();
 }

 public function msg($type ='', $msg =''){

    if(!empty($msg)){

       if(strlen(trim($type)) == 1){

         $type = str_replace(
           array('d','i','w','s'),
           array('danger','info','warning','success'),
           $type
         );
       }

       $_SESSION['msg'][$type] = $msg;

    } else {

      return $this->msg;
    }
 }

 private function flash_msg(){

    if(isset($_SESSION['msg'])){

      $this->msg = $_SESSION['msg'];

      unset($_SESSION['msg']);
    }
 }
}

$session = new Session();
$msg = $session->msg();
