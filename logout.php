<?php
session_start();

/* Catch redirect message */
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';

/* DESTROY SESSION */
session_unset();
session_destroy();

/* REDIRECT TO INDEX.PHP WITH MESSAGE PARAMETER */
if (!empty($msg)) {
    header("Location: index.php?msg=" . urlencode($msg));
} else {
    header("Location: index.php");
}
exit;
?>
