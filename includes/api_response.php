<?php
/**
 * API Response Helper
 * 
 * Standart JSON yanıt formatı ve hata yönetimi.
 * Tüm API endpointlerinde kullanılmalı.
 */

// Logger'ı yükle
require_once dirname(__FILE__) . '/logger.php';

/**
 * Başarılı JSON yanıtı döndür
 * 
 * @param array $data Yanıt verileri
 * @param int $code HTTP durum kodu
 * @return never
 */
function jsonSuccess(array $data = [], int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Hata JSON yanıtı döndür
 * 
 * @param string $message Kullanıcıya gösterilecek hata mesajı
 * @param int $code HTTP durum kodu
 * @return never
 */
function jsonError(string $message, int $code = 400): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Exception'ı yakala, logla ve güvenli yanıt döndür
 * 
 * Production'da kullanıcıya genel mesaj gösterir,
 * development'ta detaylı hata mesajı gösterir.
 * Her durumda stack trace log dosyasına yazılır.
 * 
 * @param Exception|Throwable $exception Yakalanan exception
 * @param array $context Ek bağlam bilgisi (action, user_id vb.)
 * @return never
 */
function handleApiException($exception, array $context = []): never
{
    // Her zaman logla
    logError($exception, $context);

    // Production'da genel mesaj, development'ta detaylı mesaj
    $isProd = defined('APP_ENV') ? APP_ENV === 'production' : (env('APP_ENV', 'production') === 'production');

    if ($isProd) {
        $message = 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
    } else {
        $message = $exception->getMessage();
    }

    jsonError($message, 500);
}

/**
 * Validasyon hatası döndür
 * 
 * @param string $message Validasyon hata mesajı
 * @param array $errors Detaylı hata listesi (opsiyonel)
 * @return never
 */
function jsonValidationError(string $message, array $errors = []): never
{
    http_response_code(422);
    header('Content-Type: application/json');
    $response = ['success' => false, 'error' => $message];
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Yetkilendirme hatası döndür
 * 
 * @param string $message Hata mesajı
 * @return never
 */
function jsonUnauthorized(string $message = 'Giriş yapmalısınız.'): never
{
    jsonError($message, 401);
}

/**
 * Yasak erişim hatası döndür
 * 
 * @param string $message Hata mesajı
 * @return never
 */
function jsonForbidden(string $message = 'Bu işlem için yetkiniz yok.'): never
{
    jsonError($message, 403);
}

/**
 * Bulunamadı hatası döndür
 * 
 * @param string $message Hata mesajı
 * @return never
 */
function jsonNotFound(string $message = 'Kaynak bulunamadı.'): never
{
    jsonError($message, 404);
}
