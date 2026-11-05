<?php

namespace App\Services;

final class ReceiptImage
{
    private const UPLOAD_SUBDIR = 'receipts';
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Kiểm tra xem đường dẫn ảnh biên lai có hợp lệ và an toàn không
     */
    public static function validPath(?string $path): bool
    {
        return is_string($path) && preg_match('~\A(?:uploads/)?receipts/receipt_[1-9][0-9]*_[a-f0-9]{16}\.(jpg|png|webp)\z~', $path) === 1;
    }

    /**
     * Lưu file ảnh biên lai được tải lên từ form
     *
     * @param array|null $file Thông tin file từ $_FILES['bien_lai']
     * @param int $bookingId Mã đặt sân
     * @return string Mã đường dẫn tương đối trong storage riêng, chỉ đọc qua route có kiểm tra quyền.
     * @throws ValidationException
     */
    public static function store(?array $file, int $bookingId, ?string $storageRoot = null): string
    {
        if ($file === null || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['bien_lai' => 'Vui lòng chọn ảnh chụp màn hình biên lai chuyển khoản.']);
        }

        $tmp = $file['tmp_name'] ?? null;
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($tmp) || !is_uploaded_file($tmp)) {
            throw new ValidationException(['bien_lai' => 'File tải lên bị lỗi hoặc không hợp lệ.']);
        }

        // Giới hạn 5 MB
        if ($bookingId < 1 || filesize($tmp) === false || filesize($tmp) < 1 || filesize($tmp) > 5 * 1024 * 1024) {
            throw new ValidationException(['bien_lai' => 'Kích thước ảnh biên lai không được vượt quá 5 MB.']);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        $ext = self::ALLOWED_MIMES[$mime] ?? null;

        if (!$ext) {
            throw new ValidationException(['bien_lai' => 'Định dạng ảnh không được hỗ trợ. Vui lòng chọn ảnh JPG, PNG hoặc WebP.']);
        }

        $imageSize = @getimagesize($tmp);
        if (!$imageSize || ($imageSize['mime'] ?? '') !== $mime || $imageSize[0] > 12000
            || $imageSize[1] > 12000 || $imageSize[0] * $imageSize[1] > 16000000) {
            throw new ValidationException(['bien_lai' => 'File tải lên không phải là định dạng hình ảnh hợp lệ.']);
        }

        $storageRoot ??= dirname(__DIR__, 2) . '/storage';
        $targetDir = $storageRoot . '/' . self::UPLOAD_SUBDIR;
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0700, true);
        }

        $filename = sprintf('receipt_%d_%s.%s', $bookingId, bin2hex(random_bytes(8)), $ext);
        $relativePath = self::UPLOAD_SUBDIR . '/' . $filename;
        $absolutePath = $storageRoot . '/' . $relativePath;

        if (!move_uploaded_file($tmp, $absolutePath)) {
            throw new ValidationException(['bien_lai' => 'Không thể lưu file ảnh biên lai. Vui lòng thử lại.']);
        }
        @chmod($absolutePath, 0600);

        return $relativePath;
    }

    /**
     * Trích xuất đường dẫn ảnh biên lai từ ghi chú thanh toán (nếu có)
     */
    public static function getReceiptFromNote(?string $note): ?string
    {
        if (!$note) {
            return null;
        }

        if (preg_match('~\[BIEN_LAI:([^\]]+)\]~', $note, $matches)) {
            $path = trim($matches[1]);
            return self::validPath($path) ? $path : null;
        }

        return null;
    }

    /**
     * Tìm đường dẫn ảnh biên lai cho đơn đặt sân:
     * Tương thích ảnh cũ được liên kết rõ ràng qua [BIEN_LAI:...].
     * Không quét đĩa hoặc coi file mồ côi là minh chứng.
     */
    public static function findForBooking(int $bookingId, ?string $note = null): ?string
    {
        $fromNote = self::getReceiptFromNote($note);
        if ($fromNote && self::file($fromNote, $bookingId) !== null) {
            return $fromNote;
        }

        return null;
    }

    /** Only explicitly linked files whose filename belongs to this booking are readable. */
    public static function file(string $path, int $bookingId, ?string $storageRoot = null): ?array
    {
        if (!self::validPath($path) || !str_starts_with(basename($path), 'receipt_' . $bookingId . '_')) return null;
        $root = str_starts_with($path, 'uploads/') ? dirname(__DIR__, 2) . '/public' : ($storageRoot ?? dirname(__DIR__, 2) . '/storage');
        $absolute = $root . '/' . $path;
        if (!is_file($absolute) || is_link($absolute)) return null;
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolute);
        if (!isset(self::ALLOWED_MIMES[$mime]) || !@getimagesize($absolute)) return null;
        return ['path' => $path, 'absolute' => $absolute, 'mime' => $mime];
    }

    /**
     * Lọc bỏ thẻ [BIEN_LAI:...] khỏi chuỗi ghi chú để hiển thị văn bản ghi chú sạch sẽ
     */
    public static function stripReceiptTag(?string $note): string
    {
        if (!$note) {
            return '';
        }
        return trim(preg_replace('~\[BIEN_LAI(?:_GUI_LUC)?:[^\]]+\]~', '', $note));
    }

    public static function sentAt(?string $note): ?string
    {
        if (!preg_match('~\[BIEN_LAI_GUI_LUC:([^\]]+)\]~', $note ?? '', $match)) return null;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $match[1]);
        return $date && $date->format('Y-m-d H:i:s') === $match[1] ? $match[1] : null;
    }

    /**
     * Gắn thẻ biên lai vào chuỗi ghi chú
     */
    public static function appendReceiptToNote(?string $existingNote, string $path, ?string $sentAt = null): string
    {
        $sentAt ??= self::sentAt($existingNote);
        $clean = self::stripReceiptTag($existingNote);
        $tag = "[BIEN_LAI:$path]";
        if ($sentAt !== null) $tag .= " [BIEN_LAI_GUI_LUC:$sentAt]";
        $note = $clean !== '' ? $clean . ' ' . $tag : $tag;
        if (mb_strlen($note, 'UTF-8') > 500) throw new ValidationException(['bien_lai' => 'Ghi chú thanh toán quá dài để lưu liên kết minh chứng an toàn. Vui lòng liên hệ cơ sở.']);
        return $note;
    }
}
