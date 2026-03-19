<?php
session_start();

/* DESTROY SESSION */

session_unset();
session_destroy();

/* REDIRECT LOGIN PAGE */

header("Location: login_v2.php");
exit();
?>
