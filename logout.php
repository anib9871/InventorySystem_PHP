<?php
session_start();

/* DESTROY SESSION */
session_unset();
session_destroy();
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
