<?php
/**
 * Environment Variable Loader
 * 
 * .env dosyasını parse edip environment değişkenlerini yükler.
 * External kütüphane gerektirmez.
 */

/**
 * .env dosyasını yükle
 * 
 * @param string $path .env dosyasının tam yolu
 * @return bool Dosya yüklendiyse true
 */
function loadEnv(string $path): bool
{
    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Yorum satırlarını atla
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        // KEY=VALUE formatını parse et
        if (strpos($line, '=') === false) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Çift tırnak içindeki değerleri temizle
        if (preg_match('/^"(.*)"\s*$/', $value, $matches)) {
            $value = $matches[1];
        }
        // Tek tırnak içindeki değerleri temizle
        elseif (preg_match("/^'(.*)'\s*$/", $value, $matches)) {
            $value = $matches[1];
        }

        // Environment'a yükle (eğer zaten tanımlı değilse)
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }

    return true;
}

/**
 * Environment değişkenini oku
 * 
 * @param string $key Değişken adı
 * @param mixed $default Varsayılan değer
 * @return mixed
 */
function env(string $key, $default = null)
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null) {
        return $default;
    }

    // Boolean değerleri dönüştür
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'null':
        case '(null)':
            return null;
        case 'empty':
        case '(empty)':
            return '';
    }

    return $value;
}

/**
 * Zorunlu environment değişkenini oku
 * Eksikse hata üret (production'da generic mesaj)
 * 
 * @param string $key Değişken adı
 * @return mixed
 * @throws RuntimeException
 */
function env_required(string $key)
{
    $value = env($key);

    if ($value === null || $value === '') {
        $isProd = env('APP_ENV', 'production') === 'production';

        if ($isProd) {
            // Production'da detay verme
            http_response_code(500);
            die('Yapılandırma hatası. Lütfen sistem yöneticisine başvurun.');
        } else {
            // Development'ta detaylı hata
            throw new RuntimeException("Eksik environment değişkeni: {$key}. Lütfen .env dosyasını kontrol edin.");
        }
    }

    return $value;
}
