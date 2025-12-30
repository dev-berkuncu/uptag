<?php
require_once 'admin_auth.php';
adminLogout();
header('Location: login.php');
exit;

