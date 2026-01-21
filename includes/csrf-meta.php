<?php
/**
 * CSRF Token Meta Tag ve JavaScript Helper
 * 
 * Bu dosyayı <head> içinde include edin.
 * Frontend fetch çağrılarında window.CSRF_TOKEN kullanılır.
 */
?>
<meta name="csrf-token" content="<?php echo escape(generateCsrfToken()); ?>">
<script>
    // CSRF Token'ı global değişken olarak tanımla
    window.CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Fetch helper - otomatik CSRF header ekler
    window.fetchWithCsrf = function (url, options = {}) {
        options.headers = options.headers || {};
        options.headers['X-CSRF-Token'] = window.CSRF_TOKEN;
        return fetch(url, options);
    };
</script>