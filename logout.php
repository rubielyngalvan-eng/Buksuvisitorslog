<?php
session_start();

// Destroy all session data
$_SESSION = array();
session_destroy();

// Redirect to index.php
header('Location: index.php');
exit;