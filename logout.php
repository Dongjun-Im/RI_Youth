<?php
require_once __DIR__ . '/lib.php';
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
