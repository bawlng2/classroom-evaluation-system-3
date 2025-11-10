<?php
session_start();
if (!isset($_SESSION['count'])) $_SESSION['count'] = 0;
$_SESSION['count']++;
echo 'Session count: ' . $_SESSION['count'];
?>