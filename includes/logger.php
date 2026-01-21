<?php
/**
 * Merkezi Hata Loglama Sistemi
 * 
 * Production'da stack trace'leri log dosyasına yazar,
 * kullanıcıya sadece genel hata mesajı gösterir.
 */

/**
 * Hata logla
 * 
 * @param Exception|Throwable $exception Yakalanan exception
 * @param array $context Ek bağlam bilgisi (action, user_id vb.)
 * @return void
 */
function logError($exception, array $context = []): void
{
    // Log dizinini oluştur
    $logDir = ROOT_PATH . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/error.log';

    // Log verisi
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => get_class($exception),
        'message' => $exception->getMessage(),
        'code' => $exception->getCode(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
        'context' => $context,
        'request' => [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A'
        ],
        'session_user_id' => $_SESSION['user_id'] ?? null
    ];

    // JSON formatında logla
    $logLine = json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

    // Dosyaya yaz (atomic write için LOCK_EX)
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Basit mesaj logla (exception olmadan)
 * 
 * @param string $level Log seviyesi (error, warning, info, debug)
 * @param string $message Log mesajı
 * @param array $context Ek bağlam bilgisi
 * @return void
 */
function logMessage(string $level, string $message, array $context = []): void
{
    // Log dizinini oluştur
    $logDir = ROOT_PATH . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/app.log';

    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'message' => $message,
        'context' => $context,
        'session_user_id' => $_SESSION['user_id'] ?? null
    ];

    $logLine = json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}
