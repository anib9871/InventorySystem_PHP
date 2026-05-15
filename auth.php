<?php include_once('includes/load.php'); ?>
<?php
$req_fields = array('username','password');
validate_fields($req_fields);

$username = remove_junk($_POST['username']);
$password = remove_junk($_POST['password']);

if(empty($errors)){

  $user = authenticate($username,$password);

  if($user){
      // create session
      $session->login($user['id']);

      // update last login
      updateLastLogIn($user['id']);

      $session->msg("s","Welcome to Inventory Management System");
      redirect('admin.php',false);

  }else{
      $session->msg("d","Sorry Username/Password incorrect.");
      redirect('index.php',false);
  }

}else{
   $session->msg("d",$errors);
   redirect('index.php',false);
}
?>
