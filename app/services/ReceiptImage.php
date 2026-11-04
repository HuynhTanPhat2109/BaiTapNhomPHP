<?php

namespace App\Services;

final class ReceiptImage
{
    public static function remove(string $path): void
    {
        if (self::validPath($path)) {
            $file = dirname(__DIR__, 2) . '/public/' . $path;
            if (is_file($file) && !unlink($file)) error_log('Cannot remove uncommitted receipt image.');
        }
    }
    private const UPLOAD_SUBDIR = 'uploads/receipts';
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
        return is_string($path) && preg_match('~\Auploads/receipts/receipt_[0-9]+_[a-f0-9]{16}\.(jpg|png|webp)\z~', $path) === 1;
    }

    /**
     * Lưu file ảnh biên lai được tải lên từ form
     *
     * @param array|null $file Thông tin file từ $_FILES['bien_lai']
     * @param int $bookingId Mã đặt sân
     * @return string Đường dẫn tương đối từ public/
     * @throws ValidationException
     */
    public static function store(?array $file, int $bookingId): string
    {
        if ($file === null || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['bien_lai' => 'Vui lòng chọn ảnh chụp màn hình biên lai chuyển khoản.']);
        }

        $tmp = $file['tmp_name'] ?? null;
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($tmp) || !is_uploaded_file($tmp)) {
            throw new ValidationException(['bien_lai' => 'File tải lên bị lỗi hoặc không hợp lệ.']);
        }

        // Giới hạn 5 MB
        if (filesize($tmp) > 5 * 1024 * 1024) {
            throw new ValidationException(['bien_lai' => 'Kích thước ảnh biên lai không được vượt quá 5 MB.']);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        $ext = self::ALLOWED_MIMES[$mime] ?? null;

        if (!$ext) {
            throw new ValidationException(['bien_lai' => 'Định dạng ảnh không được hỗ trợ. Vui lòng chọn ảnh JPG, PNG hoặc WebP.']);
        }

        $imageSize = @getimagesize($tmp);
        if (!$imageSize) {
            throw new ValidationException(['bien_lai' => 'File tải lên không phải là định dạng hình ảnh hợp lệ.']);
        }

        $targetDir = dirname(__DIR__, 2) . '/public/' . self::UPLOAD_SUBDIR;
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $filename = sprintf('receipt_%d_%s.%s', $bookingId, bin2hex(random_bytes(8)), $ext);
        $relativePath = self::UPLOAD_SUBDIR . '/' . $filename;
        $absolutePath = dirname(__DIR__, 2) . '/public/' . $relativePath;

        if (!move_uploaded_file($tmp, $absolutePath)) {
            throw new ValidationException(['bien_lai' => 'Không thể lưu file ảnh biên lai. Vui lòng thử lại.']);
        }

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
     * Ưu tiên 1: Đọc từ thẻ [BIEN_LAI:...] trong ghi chú
     * Ưu tiên 2: Quét file trên đĩa uploads/receipts/receipt_{bookingId}_*
     */
    public static function findForBooking(int $bookingId, ?string $note = null): ?string
    {
        $fromNote = self::getReceiptFromNote($note);
        if ($fromNote && is_file(dirname(__DIR__, 2) . '/public/' . $fromNote)) {
            return $fromNote;
        }

        if ($bookingId > 0) {
            $pattern = dirname(__DIR__, 2) . '/public/' . self::UPLOAD_SUBDIR . '/receipt_' . $bookingId . '_*';
            $matches = glob($pattern);
            if (!empty($matches) && is_file($matches[0])) {
                $rel = self::UPLOAD_SUBDIR . '/' . basename($matches[0]);
                return self::validPath($rel) ? $rel : null;
            }
        }

        return null;
    }

    /**
     * Lọc bỏ thẻ [BIEN_LAI:...] khỏi chuỗi ghi chú để hiển thị văn bản ghi chú sạch sẽ
     */
    public static function stripReceiptTag(?string $note): string
    {
        if (!$note) {
            return '';
        }
        return trim(preg_replace('~\[BIEN_LAI:[^\]]+\]~', '', $note));
    }

    /**
     * Gắn thẻ biên lai vào chuỗi ghi chú
     */
    public static function appendReceiptToNote(?string $existingNote, string $path): string
    {
        $clean = trim((string) $existingNote);
        // Xóa thẻ biên lai cũ nếu có
        $clean = trim(preg_replace('~\[BIEN_LAI:[^\]]+\]~', '', $clean));
        $tag = "[BIEN_LAI:$path]";
        return $clean !== '' ? $clean . ' ' . $tag : $tag;
    }
}
