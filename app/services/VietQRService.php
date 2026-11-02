<?php

namespace App\Services;

final class VietQRService
{
    private const API_URL = 'https://api.vietqr.io/v2/banks';
    private const CACHE_TTL = 86400; // 24 giờ
    private string $cachePath;

    public function __construct(?string $cachePath = null)
    {
        $this->cachePath = $cachePath ?? (dirname(__DIR__, 2) . '/storage/temp/vietqr_banks_cache.json');
    }

    /**
     * Lấy danh sách ngân hàng Việt Nam hỗ trợ VietQR.
     * Tự động cache vào file local để không gọi API ngoài liên tục.
     * Có cơ chế fallback danh sách ngân hàng chuẩn nếu API ngoài gặp sự cố.
     */
    public function getBankList(): array
    {
        // 1. Kiểm tra cache còn hạn
        if (file_exists($this->cachePath) && (time() - filemtime($this->cachePath) < self::CACHE_TTL)) {
            $cachedContent = @file_get_contents($this->cachePath);
            if ($cachedContent) {
                $decoded = json_decode($cachedContent, true);
                if (is_array($decoded) && !empty($decoded)) {
                    return $decoded;
                }
            }
        }

        // 2. Gọi API VietQR chính thức
        $banks = $this->fetchFromApi();

        if (!empty($banks)) {
            $dir = dirname($this->cachePath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            @file_put_contents($this->cachePath, json_encode($banks, JSON_UNESCAPED_UNICODE));
            return $banks;
        }

        // 3. Nếu API lỗi, thử dùng lại cache cũ (kể cả đã quá hạn)
        if (file_exists($this->cachePath)) {
            $cachedContent = @file_get_contents($this->cachePath);
            if ($cachedContent) {
                $decoded = json_decode($cachedContent, true);
                if (is_array($decoded) && !empty($decoded)) {
                    return $decoded;
                }
            }
        }

        // 4. Fallback danh mục ngân hàng chuẩn phổ biến nhất tại Việt Nam
        return self::fallbackBanks();
    }

    /**
     * Tìm kiếm ngân hàng trong danh mục theo mã BIN hoặc mã viết tắt (VCB, MB, TCB...).
     */
    public function findBank(string $codeOrBin): ?array
    {
        $needle = strtoupper(trim($codeOrBin));
        $banks = $this->getBankList();

        foreach ($banks as $bank) {
            $bin = $bank['bin'] ?? '';
            $code = strtoupper($bank['code'] ?? '');
            $shortName = strtoupper($bank['shortName'] ?? '');

            if ($bin === $needle || $code === $needle || $shortName === $needle) {
                return $bank;
            }
        }

        return null;
    }

    /**
     * Tạo đường dẫn ảnh mã QR VietQR động chuẩn NAPAS 247 theo chuẩn VietQR.io QuickLink.
     *
     * @param string $bin Mã BIN ngân hàng (6 chữ số)
     * @param string $accountNumber Số tài khoản thụ hưởng
     * @param float|int|string $amount Số tiền thanh toán
     * @param string $description Nội dung chuyển khoản (Mã tham chiếu đơn đặt sân)
     * @param string $accountName Tên người nhận (in hoa không dấu)
     * @param string $template Giao diện mẫu (compact2, qr_only, print)
     * @return string URL ảnh QR trực tiếp
     */
    public function generateQuickLink(
        string $bin,
        string $accountNumber,
        float|int|string $amount,
        string $description,
        string $accountName,
        string $template = 'compact2'
    ): string {
        $cleanBin = preg_replace('/\D/', '', $bin);
        $cleanAccount = preg_replace('/[^a-zA-Z0-9]/', '', $accountNumber);
        $cleanAmount = (int) round((float) $amount);
        $cleanDesc = trim($description);
        $cleanName = mb_strtoupper(trim($accountName), 'UTF-8');

        $query = http_build_query([
            'amount' => $cleanAmount,
            'addInfo' => $cleanDesc,
            'accountName' => $cleanName,
        ]);

        return "https://img.vietqr.io/image/{$cleanBin}-{$cleanAccount}-{$template}.png?{$query}";
    }

    /**
     * Tải nội dung ảnh mã VietQR từ img.vietqr.io (có cache cục bộ để phục vụ proxy 'self').
     */
    public function fetchQrImage(
        string $bin,
        string $accountNumber,
        float|int|string $amount,
        string $description,
        string $accountName,
        string $template = 'compact2'
    ): ?string {
        $url = $this->generateQuickLink($bin, $accountNumber, $amount, $description, $accountName, $template);
        $cacheKey = md5($url);
        $cacheFile = dirname(__DIR__, 2) . '/storage/temp/vietqr_' . $cacheKey . '.png';

        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            $data = file_get_contents($cacheFile);
            if ($data !== false && strlen($data) > 100) {
                return $data;
            }
        }

        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; STRYKE/1.0)',
        ]);
        $result = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode === 200 && is_string($result) && strlen($result) > 100) {
            @file_put_contents($cacheFile, $result);
            return $result;
        }

        return null;
    }

    /**
     * Kiểm tra và chuẩn hóa dữ liệu khi Chủ sân thêm/sửa tài khoản ngân hàng.
     *
     * @param array $data ['bank_code' => 'VCB', 'so_tai_khoan' => '...', 'ten_chu_tai_khoan' => '...']
     * @return array Dữ liệu đã validate và tự động map mã BIN:
     *               ['ten_ngan_hang' => ..., 'ma_bin' => ..., 'so_tai_khoan' => ..., 'ten_chu_tai_khoan' => ...]
     * @throws ValidationException
     */
    public function validateAccountInput(array $data): array
    {
        $errors = [];

        // 1. Kiểm tra ngân hàng
        $bankKey = trim((string) ($data['bank_code'] ?? $data['ma_bin'] ?? ''));
        if ($bankKey === '') {
            $errors['bank_code'] = 'Vui lòng chọn ngân hàng.';
            $bank = null;
        } else {
            $bank = $this->findBank($bankKey);
            if (!$bank || empty($bank['bin'])) {
                $errors['bank_code'] = 'Ngân hàng không hợp lệ hoặc chưa được VietQR hỗ trợ.';
            }
        }

        // 2. Kiểm tra số tài khoản
        $accountNo = trim((string) ($data['so_tai_khoan'] ?? ''));
        if ($accountNo === '') {
            $errors['so_tai_khoan'] = 'Vui lòng nhập số tài khoản ngân hàng.';
        } elseif (!preg_match('/^[a-zA-Z0-9]{6,30}$/', $accountNo)) {
            $errors['so_tai_khoan'] = 'Số tài khoản không hợp lệ (từ 6 đến 30 ký tự số/chữ).';
        }

        // 3. Kiểm tra tên chủ tài khoản
        $accountName = trim((string) ($data['ten_chu_tai_khoan'] ?? ''));
        if ($accountName === '') {
            $errors['ten_chu_tai_khoan'] = 'Vui lòng nhập tên chủ tài khoản.';
        } elseif (mb_strlen($accountName, 'UTF-8') > 150) {
            $errors['ten_chu_tai_khoan'] = 'Tên chủ tài khoản không vượt quá 150 ký tự.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $bankName = $bank['shortName'] ?? $bank['name'] ?? $bank['code'];

        return [
            'ten_ngan_hang' => $bankName,
            'ma_bin' => (string) $bank['bin'],
            'so_tai_khoan' => $accountNo,
            'ten_chu_tai_khoan' => mb_strtoupper($accountName, 'UTF-8'),
        ];
    }

    /**
     * Gọi API VietQR để lấy danh sách ngân hàng mới nhất.
     */
    private function fetchFromApi(): array
    {
        if (!function_exists('curl_init')) {
            return [];
        }

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT => 'STRYKE-Platform/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200 || !$response) {
            return [];
        }

        $data = json_decode($response, true);
        if (!isset($data['code']) || $data['code'] !== '00' || !is_array($data['data'])) {
            return [];
        }

        $result = [];
        foreach ($data['data'] as $item) {
            if (!empty($item['bin']) && !empty($item['shortName'])) {
                $result[] = [
                    'id' => $item['id'] ?? null,
                    'name' => $item['name'] ?? '',
                    'code' => $item['code'] ?? '',
                    'bin' => (string) $item['bin'],
                    'shortName' => $item['shortName'] ?? '',
                    'logo' => $item['logo'] ?? '',
                    'transferSupported' => !empty($item['transferSupported']) ? 1 : 0,
                ];
            }
        }

        return $result;
    }

    /**
     * Danh sách ngân hàng chuẩn phổ biến làm fallback dự phòng khi không có internet.
     */
    public static function fallbackBanks(): array
    {
        return [
            ['bin' => '970436', 'code' => 'VCB', 'shortName' => 'Vietcombank', 'name' => 'Ngân hàng TMCP Ngoại thương Việt Nam', 'logo' => 'https://api.vietqr.io/img/VCB.png'],
            ['bin' => '970422', 'code' => 'MB', 'shortName' => 'MBBank', 'name' => 'Ngân hàng TMCP Quân đội', 'logo' => 'https://api.vietqr.io/img/MB.png'],
            ['bin' => '970407', 'code' => 'TCB', 'shortName' => 'Techcombank', 'name' => 'Ngân hàng TMCP Kỹ thương Việt Nam', 'logo' => 'https://api.vietqr.io/img/TCB.png'],
            ['bin' => '970415', 'code' => 'ICB', 'shortName' => 'VietinBank', 'name' => 'Ngân hàng TMCP Công thương Việt Nam', 'logo' => 'https://api.vietqr.io/img/ICB.png'],
            ['bin' => '970418', 'code' => 'BIDV', 'shortName' => 'BIDV', 'name' => 'Ngân hàng TMCP Đầu tư và Phát triển Việt Nam', 'logo' => 'https://api.vietqr.io/img/BIDV.png'],
            ['bin' => '970416', 'code' => 'ACB', 'shortName' => 'ACB', 'name' => 'Ngân hàng TMCP Á Châu', 'logo' => 'https://api.vietqr.io/img/ACB.png'],
            ['bin' => '970432', 'code' => 'VPB', 'shortName' => 'VPBank', 'name' => 'Ngân hàng TMCP Việt Nam Thịnh Vượng', 'logo' => 'https://api.vietqr.io/img/VPB.png'],
            ['bin' => '970423', 'code' => 'TPB', 'shortName' => 'TPBank', 'name' => 'Ngân hàng TMCP Tiên Phong', 'logo' => 'https://api.vietqr.io/img/TPB.png'],
            ['bin' => '970405', 'code' => 'VBA', 'shortName' => 'Agribank', 'name' => 'Ngân hàng Nông nghiệp và Phát triển Nông thôn VN', 'logo' => 'https://api.vietqr.io/img/VBA.png'],
            ['bin' => '970403', 'code' => 'STB', 'shortName' => 'Sacombank', 'name' => 'Ngân hàng TMCP Sài Gòn Thương Tín', 'logo' => 'https://api.vietqr.io/img/STB.png'],
            ['bin' => '970437', 'code' => 'HDB', 'shortName' => 'HDBank', 'name' => 'Ngân hàng TMCP Phát triển TP.HCM', 'logo' => 'https://api.vietqr.io/img/HDB.png'],
            ['bin' => '970441', 'code' => 'VIB', 'shortName' => 'VIB', 'name' => 'Ngân hàng TMCP Quốc tế Việt Nam', 'logo' => 'https://api.vietqr.io/img/VIB.png'],
            ['bin' => '970443', 'code' => 'SHB', 'shortName' => 'SHB', 'name' => 'Ngân hàng TMCP Sài Gòn - Hà Nội', 'logo' => 'https://api.vietqr.io/img/SHB.png'],
        ];
    }
}
