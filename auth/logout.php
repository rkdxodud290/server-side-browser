<?php
require_once 'session_config.php';
$_SESSION = [];
session_destroy();
header("Location: ../login.html");
exit;
?>
