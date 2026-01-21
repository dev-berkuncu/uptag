<?php
/**
 * Bootstrap 5.3 CSS/JS CDN
 * Tek layout sistemi olarak kullanılıyor
 * Bu dosyayı <head> içinde, style.css'ten ÖNCE dahil edin
 */
?>
<!-- Bootstrap 5.3 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<?php
// CSRF Token için meta tag ve JS helper
require_once dirname(__FILE__) . '/csrf-meta.php';
?>