<?php
session_start();

/* Catch redirect message */
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';

/* DESTROY SESSION */
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Logging Out...</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body style="background: #f8fafc;">

<script>
// 1. CLEAR LOCALSTORAGE
Object.keys(localStorage).forEach(function(key){
    if(
        key.startsWith("inventory_") ||
        key.startsWith("billing_")
    ){
        localStorage.removeItem(key);
    }
});

// 2. CHECK IF REDEPLOYMENT HAPPENED
var msg = "<?php echo $msg; ?>";

if (msg === "updated") {
    // 🚀 REDEPLOYMENT TOAST ALERT
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'info',
        title: '🚀 System Updated!',
        text: 'New version deployed. Please login again.',
        showConfirmButton: true,
        confirmButtonText: 'OK, Login',
        confirmButtonColor: '#2563eb',
        timer: 5000,
        timerProgressBar: true
    }).then(function() {
        window.location.href = "login_v2.php";
    });
} else {
    // NORMAL LOGOUT -> DIRECT REDIRECT
    window.location.href = "login_v2.php";
}
</script>

</body>
</html>
