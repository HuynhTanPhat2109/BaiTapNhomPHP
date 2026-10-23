<?php

namespace App\Services;

final class FacilityImage
{
    public static function validPath(?string $path): bool
    {
        return is_string($path) && preg_match('~\Auploads/co-so/[a-f0-9]{32}\.(jpg|png|webp)\z~', $path) === 1;
    }

    public static function store(?array $file, string $field = 'anh_dai_dien'): ?string
    {
        if ($file === null || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) return null;
        $tmp = $file['tmp_name'] ?? null;
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($tmp) || !is_uploaded_file($tmp)
            || filesize($tmp) > 3 * 1024 * 1024) {
            throw new ValidationException([$field => 'Ảnh tải lên không hợp lệ hoặc vượt quá 3 MB.']);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        $size = @getimagesize($tmp);
        if (!$extension || !$size || $size[0] > 6000 || $size[1] > 6000 || $size[0] * $size[1] > 20000000) {
            throw new ValidationException([$field => 'Chọn JPEG, PNG hoặc WebP, tối đa 6000 px mỗi chiều và 20 megapixel.']);
        }
        $path = 'uploads/co-so/' . bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($tmp, dirname(__DIR__, 2) . '/public/' . $path)) {
            throw new ValidationException([$field => 'Không lưu được ảnh. Vui lòng thử lại.']);
        }
        return $path;
    }

    public static function remove(?string $path): void
    {
        if (self::validPath($path)) {
            $file = dirname(__DIR__, 2) . '/public/' . $path;
            if (is_file($file) && !@unlink($file)) error_log('Could not remove facility image.');
        }
    }
}
