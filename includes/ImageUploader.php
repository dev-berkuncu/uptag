<?php
/**
 * Güvenli Image Upload Sınıfı
 * 
 * Tüm image upload işlemleri için merkezi güvenlik kontrollerini sağlar:
 * - finfo_file() ile gerçek MIME doğrulama
 * - Uzantı whitelist
 * - Random dosya adı üretimi
 * - Maksimum boyut kontrolü
 * - GD ile yeniden encode (zararlı payload temizleme)
 */

class ImageUploader
{
    /**
     * İzin verilen MIME tipleri ve karşılık gelen uzantılar
     */
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];

    /**
     * Varsayılan maksimum dosya boyutu (5MB)
     */
    private const DEFAULT_MAX_SIZE = 5 * 1024 * 1024;

    /**
     * Varsayılan WEBP kalitesi
     */
    private const DEFAULT_QUALITY = 85;

    /**
     * Upload klasörünün base path'i
     */
    private string $basePath;

    /**
     * Constructor
     * 
     * @param string|null $basePath Upload klasörünün base path'i (null ise public/uploads kullanılır)
     */
    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? dirname(__DIR__) . '/public/uploads';
    }

    /**
     * Görsel yükle
     * 
     * @param array $file $_FILES dizisinden gelen dosya
     * @param string $folder Hedef klasör (avatars, posts, comments, ads, banners)
     * @param array $options Opsiyonlar:
     *   - maxSize: Maksimum boyut (bytes)
     *   - quality: WEBP/JPEG kalitesi (1-100)
     *   - outputFormat: Çıktı formatı (webp, jpg, png) - varsayılan: webp
     *   - maxWidth: Maksimum genişlik (resize için)
     *   - maxHeight: Maksimum yükseklik (resize için)
     * @return array ['success' => bool, 'filename' => string|null, 'error' => string|null]
     */
    public function upload(array $file, string $folder, array $options = []): array
    {
        // Varsayılan opsiyonlar
        $maxSize = $options['maxSize'] ?? self::DEFAULT_MAX_SIZE;
        $quality = $options['quality'] ?? self::DEFAULT_QUALITY;
        $outputFormat = $options['outputFormat'] ?? 'webp';
        $maxWidth = $options['maxWidth'] ?? null;
        $maxHeight = $options['maxHeight'] ?? null;

        // 1. Upload hatası kontrolü
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->error($this->getUploadErrorMessage($file['error']));
        }

        // 2. Dosya boyutu kontrolü
        if ($file['size'] > $maxSize) {
            $maxSizeMB = round($maxSize / (1024 * 1024), 1);
            return $this->error("Dosya boyutu {$maxSizeMB}MB'dan küçük olmalıdır.");
        }

        // 3. finfo_file() ile GERÇEK MIME doğrulama
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file['tmp_name']);

        if (!isset(self::ALLOWED_MIMES[$realMime])) {
            return $this->error('Sadece JPG, PNG, GIF veya WebP dosyaları yüklenebilir.');
        }

        // 4. Random dosya adı üret
        $randomName = bin2hex(random_bytes(16));
        $extension = $outputFormat === 'original' ? self::ALLOWED_MIMES[$realMime] : $outputFormat;
        $filename = $randomName . '.' . $extension;

        // 5. Hedef klasörü oluştur
        $targetDir = $this->basePath . '/' . $folder;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $targetPath = $targetDir . '/' . $filename;

        // 6. GD ile yeniden encode
        $result = $this->processImage(
            $file['tmp_name'],
            $targetPath,
            $realMime,
            $outputFormat,
            $quality,
            $maxWidth,
            $maxHeight
        );

        if (!$result) {
            return $this->error('Görsel işlenemedi. Lütfen geçerli bir görsel dosyası yükleyin.');
        }

        return [
            'success' => true,
            'filename' => $filename,
            'path' => 'uploads/' . $folder . '/' . $filename,
            'error' => null
        ];
    }

    /**
     * GD ile görseli işle ve yeniden encode et
     */
    private function processImage(
        string $sourcePath,
        string $targetPath,
        string $sourceMime,
        string $outputFormat,
        int $quality,
        ?int $maxWidth,
        ?int $maxHeight
    ): bool {
        // Kaynak görseli yükle
        $sourceImage = $this->createImageFromFile($sourcePath, $sourceMime);
        if (!$sourceImage) {
            return false;
        }

        // Boyutları al
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);

        // Resize gerekiyorsa
        if ($maxWidth || $maxHeight) {
            $newDimensions = $this->calculateDimensions($width, $height, $maxWidth, $maxHeight);
            if ($newDimensions['width'] !== $width || $newDimensions['height'] !== $height) {
                $resizedImage = imagecreatetruecolor($newDimensions['width'], $newDimensions['height']);

                // Şeffaflığı koru (PNG/GIF/WebP için)
                $this->preserveTransparency($resizedImage, $outputFormat);

                imagecopyresampled(
                    $resizedImage,
                    $sourceImage,
                    0,
                    0,
                    0,
                    0,
                    $newDimensions['width'],
                    $newDimensions['height'],
                    $width,
                    $height
                );

                imagedestroy($sourceImage);
                $sourceImage = $resizedImage;
            }
        }

        // Çıktı görseli kaydet
        $result = $this->saveImage($sourceImage, $targetPath, $outputFormat, $quality);

        imagedestroy($sourceImage);

        return $result;
    }

    /**
     * Dosyadan GD image oluştur
     */
    private function createImageFromFile(string $path, string $mime)
    {
        switch ($mime) {
            case 'image/jpeg':
                return @imagecreatefromjpeg($path);
            case 'image/png':
                return @imagecreatefrompng($path);
            case 'image/gif':
                return @imagecreatefromgif($path);
            case 'image/webp':
                return @imagecreatefromwebp($path);
            default:
                return false;
        }
    }

    /**
     * GD image'ı dosyaya kaydet
     */
    private function saveImage($image, string $path, string $format, int $quality): bool
    {
        switch ($format) {
            case 'webp':
                return @imagewebp($image, $path, $quality);
            case 'jpg':
            case 'jpeg':
                return @imagejpeg($image, $path, $quality);
            case 'png':
                // PNG için quality 0-9 arasında (9 = en yüksek sıkıştırma)
                $pngQuality = (int) round((100 - $quality) / 11.11);
                return @imagepng($image, $path, $pngQuality);
            case 'gif':
                return @imagegif($image, $path);
            default:
                return @imagewebp($image, $path, $quality);
        }
    }

    /**
     * Şeffaflığı koru
     */
    private function preserveTransparency($image, string $format): void
    {
        if (in_array($format, ['png', 'gif', 'webp'])) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefill($image, 0, 0, $transparent);
        }
    }

    /**
     * Resize için yeni boyutları hesapla (aspect ratio'yu koru)
     */
    private function calculateDimensions(int $width, int $height, ?int $maxWidth, ?int $maxHeight): array
    {
        $newWidth = $width;
        $newHeight = $height;

        if ($maxWidth && $width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) round($height * ($maxWidth / $width));
        }

        if ($maxHeight && $newHeight > $maxHeight) {
            $newHeight = $maxHeight;
            $newWidth = (int) round($newWidth * ($maxHeight / $newHeight));
        }

        return ['width' => $newWidth, 'height' => $newHeight];
    }

    /**
     * Upload hata mesajlarını çevir
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Dosya sunucu limitini aşıyor.',
            UPLOAD_ERR_FORM_SIZE => 'Dosya form limitini aşıyor.',
            UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi.',
            UPLOAD_ERR_NO_FILE => 'Dosya seçilmedi.',
            UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör bulunamadı.',
            UPLOAD_ERR_CANT_WRITE => 'Dosya yazılamadı.',
            UPLOAD_ERR_EXTENSION => 'Yükleme bir uzantı tarafından durduruldu.',
        ];

        return $messages[$errorCode] ?? 'Bilinmeyen yükleme hatası.';
    }

    /**
     * Hata döndür
     */
    private function error(string $message): array
    {
        return [
            'success' => false,
            'filename' => null,
            'path' => null,
            'error' => $message
        ];
    }
}
