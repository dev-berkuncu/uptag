<?php
/**
 * Admin Kimlik Doğrulama Yardımcısı
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Admin giriş yapmış mı kontrol eder
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true;
}

/**
 * Admin girişi zorunlu kılar, değilse login sayfasına yönlendirir
 */
function requireAdminAuth() {
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Admin çıkışı yapar
 */
function adminLogout() {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_auth']);
}
