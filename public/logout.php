<?php
require_once '../config/config.php';

$user = new User();
$user->logout();

header('Location: ' . BASE_URL . '/index.php');
exit;


