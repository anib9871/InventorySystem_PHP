<?php
// ✅ Same session name jo login mein use kiya
session_name('SECURE_APP_SESSION');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

/* DESTROY SESSION */
session_unset();
session_destroy();

// ✅ Cookie bhi manually delete karo
if(isset($_COOKIE[session_name()])){
    setcookie(session_name(), '', time()-3600, '/');
}
?>

<script>
Object.keys(localStorage).forEach(function(key){

    if(
        key.startsWith("inventory_") ||
        key.startsWith("billing_")
    ){
        localStorage.removeItem(key);
    }

});

// REDIRECT
window.location.href = "login_v2.php";

</script>
