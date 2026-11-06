<?php
namespace App\Controllers;

use App\Services\{BookingService, CourtScheduleService};
use Core\{Application, Controller, HttpException};

final class BookingController extends Controller
{
    public function __construct(private Application $app, private ?\App\Services\BookingReceiptService $receipts = null) {}
    private function receipts(): \App\Services\BookingReceiptService { return $this->receipts ??= new \App\Services\BookingReceiptService($this->app->pdo()); }
    private function service(): BookingService { return new BookingService($this->app->pdo()); }
    private function json(array $data, int $status = 200): void
    {
        http_response_code($status); header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
    private function selection(): array
    {
        $raw=post_string('selection');
        if (strlen($raw)>40000) throw new HttpException(422,'Lựa chọn quá lớn.');
        try { $value=json_decode($raw,true,16,JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new HttpException(422,'Lựa chọn không hợp lệ.'); }
        if (!is_array($value)) throw new HttpException(422,'Lựa chọn không hợp lệ.');
        return BookingService::selection($value);
    }
    public function quote(): void
    {
        try { $quote=$this->service()->quote($this->selection()); $this->json(['total'=>$quote['total']]); }
        catch (HttpException $e) { $this->json(['error'=>$e->getMessage()],$e->status); }
    }
    public function start(): void
    {
        $input=$this->selection();
        $quote=$this->service()->quote($input);
        $user=$this->app->auth()->user();
        if ($user && $user['ten_vai_tro']!=='KHACH_HANG') throw new HttpException(403,'Chức năng này dành cho tài khoản khách hàng.');
        $token=bin2hex(random_bytes(32));
        $drafts=$_SESSION['booking_drafts'] ?? [];
        $drafts=array_filter($drafts,static fn($draft)=>($draft['created']??0)>time()-7200);
        if (count($drafts)>=10) array_shift($drafts);
        $drafts[$token]=['input'=>$input,'extras'=>['services'=>[],'note'=>''],'fingerprint'=>$quote['fingerprint'],
            'created'=>time(),'owner'=>$user ? (int)$user['ma_khach_hang'] : null];
        $_SESSION['booking_drafts']=$drafts;
        $this->go('/booking/details','draft',$token);
    }
    private function go(string $route, string $field, string $value): void
    {
        header('Location: '.url($route).'&'.$field.'='.rawurlencode($value),true,303); exit;
    }
    private function draft(): array
    {
        $key=$_POST['draft'] ?? $_GET['draft'] ?? '';
        if (!is_string($key) || !preg_match('/\A[a-f0-9]{64}\z/',$key)) throw new HttpException(404,'Không tìm thấy lựa chọn.');
        $draft=$_SESSION['booking_drafts'][$key] ?? null;
        if (!$draft || $draft['created']<time()-7200) throw new HttpException(410,'Lựa chọn đã hết hạn. Vui lòng chọn lịch lại.');
        $user=$this->app->auth()->user();
        if ($draft['owner'] !== null && (!$user || (int)$user['ma_khach_hang']!==$draft['owner'])) throw new HttpException(403,'Lựa chọn không thuộc tài khoản này.');
        if ($user && $user['ten_vai_tro']!=='KHACH_HANG') throw new HttpException(403,'Vui lòng sử dụng tài khoản khách hàng.');
        return [$key,$draft,$user];
    }
    private function page(string $key, array $draft, ?array $user, ?string $error = null, int $status = 200): void
    {
        $quote=null;
        try { $quote=$this->service()->quote($draft['input'],$draft['extras']); }
        catch (HttpException $e) { $error=$e->getMessage(); $status=$e->status; }
        if ($quote) {
            $draft['fingerprint']=$quote['fingerprint'];
            $_SESSION['booking_drafts'][$key]=$draft;
        }
        header('Cache-Control: no-store');
        $bookingBackUrl = $this->scheduleUrl($draft['input']['facility'], $draft['input']['date']);
        $this->render('courts/booking-details',compact('key','draft','user','quote','error','bookingBackUrl')+['title'=>'Chi tiết lịch đặt sân'],$status);
    }
    public function details(): void
    {
        [$key,$draft,$user]=$this->draft();
        if (isset($draft['booking']) && $user) $this->go('/booking/wait','id',(string)$draft['booking']);
        $this->page($key,$draft,$user);
    }
    public function review(): void
    {
        [$key,$draft,$user]=$this->draft();
        $draft['extras']=BookingService::extras($_POST['services'] ?? [],post_string('note'));
        $_SESSION['booking_drafts'][$key]=$draft;
        if (in_array(post_string('action'),['login','register'],true) && !$user) {
            $_SESSION['booking_return']=$key;
            $this->redirect('/'.post_string('action'));
        }
        $this->page($key,$draft,$user);
    }
    public function confirm(): void
    {
        [$key,$draft,$user]=$this->draft();
        if (!$user) { $_SESSION['booking_return']=$key; $this->redirect('/login'); }
        try {
            $extras=BookingService::extras($_POST['services'] ?? [],post_string('note'));
            // Any changed services require a review first, including when totals happen to match.
            if ($extras!==$draft['extras']) {
                $draft['extras']=$extras;
                $this->page($key,$draft,$user,'Đã cập nhật dịch vụ/ghi chú. Vui lòng xem lại và bấm xác nhận lần nữa.',409);
                return;
            }
            $id=$this->service()->confirm((int)$user['ma_khach_hang'],$key,$draft['input'],$extras,post_string('quote'));
            $_SESSION['booking_drafts'][$key]['booking']=$id;
            $_SESSION['booking_drafts'][$key]['owner']=(int)$user['ma_khach_hang'];
            $this->preparePayment($id, (int)$user['ma_khach_hang']);
            $this->go('/booking/wait','id',(string)$id);
        } catch (HttpException $e) { $this->page($key,$draft,$user,$e->getMessage(),$e->status); }
    }
    /** POST-only handoff. Booking stays idempotent and its original deadline never changes. */
    private function preparePayment(int $id, int $customer): void
    {
        unset($_SESSION['booking_payment_error'][$id]);
        $booking = $this->service()->owned($id, $customer);
        if ($booking['trang_thai_dat_san'] !== BookingService::PENDING) return;
        try {
            (new \App\Services\PaymentService($this->app->pdo()))->createPaymentRequest($id, $booking['tong_tien_tam_tinh']);
        } catch (\App\Services\ValidationException $e) {
            $_SESSION['booking_payment_error'][$id] = 'Yêu cầu thanh toán chưa thể tạo. Vui lòng kiểm tra trạng thái booking hoặc liên hệ cơ sở; thời hạn giữ sân không được gia hạn.';
        } catch (\Throwable $e) {
            error_log('Payment handoff failed: ' . get_class($e));
            $_SESSION['booking_payment_error'][$id] = 'Kết nối thanh toán tạm thời gặp lỗi. Bạn có thể thử lại trong thời gian giữ sân còn hiệu lực.';
        }
    }

    public function retryPayment(): void
    {
        $user = $this->app->auth()->user();
        if (!$user || $user['ten_vai_tro'] !== 'KHACH_HANG') throw new HttpException(403, 'Vui lòng đăng nhập tài khoản khách hàng.');
        $id = post_string('id');
        if (!\App\Services\CoSoService::validId($id)) throw new HttpException(404, 'Không tìm thấy booking.');
        $this->preparePayment((int)$id, (int)$user['ma_khach_hang']);
        $this->go('/booking/wait', 'id', $id);
    }

    public function waiting(): void
    {
        $user=$this->app->auth()->user();
        if (!$user || $user['ten_vai_tro']!=='KHACH_HANG') throw new HttpException(403,'Vui lòng đăng nhập tài khoản khách hàng.');
        $id=$_GET['id']??'';
        if (!is_string($id) || !\App\Services\CoSoService::validId($id)) throw new HttpException(404,'Không tìm thấy booking.');
        $booking=$this->service()->inspect((int)$id,(int)$user['ma_khach_hang']);
        $now=CourtScheduleService::now();
        header('Cache-Control: no-store');
        if (($_GET['status']??'')==='1') {
            $paymentStatus = null;
            $paymentNote = null;
            $receiptSubmitted = false;
            try {
                $paymentService = new \App\Services\PaymentService($this->app->pdo());
                $payment = $paymentService->getPaymentByBooking((int) $id);
                $paymentStatus = $payment['trang_thai'] ?? null;
                $states = (new \App\Models\Booking($this->app->pdo()))->effectiveStates((int) $id);
                if ($states) {
                    $booking['trang_thai_dat_san'] = $states['booking_status'];
                    $paymentStatus = $states['payment_status'];
                }
                if (!empty($payment['ghi_chu'])) {
                    $paymentNote = \App\Services\ReceiptImage::stripReceiptTag($payment['ghi_chu']);
                }
                $receiptSubmitted = $payment && $this->receipts()->info($payment) !== null;
            } catch (\Throwable $e) {}

            $currentState = $booking['trang_thai_dat_san'];

            $this->json([
                'state' => $currentState,
                'payment_status' => $paymentStatus,
                'payment_note' => $paymentNote,
                'receipt_submitted' => $receiptSubmitted,
                'now' => $now->format(DATE_ATOM),
                'expires' => $booking['het_han_giu_san_luc']
            ]);
            return;
        }
        $bookingBackUrl = $this->scheduleUrl((int)$booking['ma_co_so'], $booking['lines'][0]['ngay_choi'] ?? $now->format('Y-m-d'));

        // Tích hợp thanh toán VietQR cho cơ sở sân
        $payment = null;
        $bankAccount = null;
        $qrUrl = null;
        $paymentError = $_SESSION['booking_payment_error'][(int)$id] ?? null;
        unset($_SESSION['booking_payment_error'][(int)$id]);
        $receiptNotice = $_SESSION['booking_receipt_notice'][(int)$id] ?? null;
        unset($_SESSION['booking_receipt_notice'][(int)$id]);
        $receiptImage = null;

        try {
            $paymentService = new \App\Services\PaymentService($this->app->pdo());
            $payment = $paymentService->getPaymentByBooking((int) $id);
            $states = (new \App\Models\Booking($this->app->pdo()))->effectiveStates((int) $id);
            if ($states) {
                $booking['trang_thai_dat_san'] = $states['booking_status'];
                if ($payment) $payment['trang_thai'] = $states['payment_status'];
            }
            if ($payment && $payment['trang_thai'] === 'CHO_THANH_TOAN') {
                $bankModel = new \App\Models\TaiKhoanNganHang($this->app->pdo());
                $bankAccount = $bankModel->getDefaultByFacility((int) $booking['ma_co_so']);
                if ($bankAccount) {
                    $vietqr = new \App\Services\VietQRService();
                    $qrUrl = $vietqr->generateQuickLink(
                        $bankAccount['ma_bin'],
                        $bankAccount['so_tai_khoan'],
                        $payment['so_tien'],
                        $payment['ma_tham_chieu'],
                        $bankAccount['ten_chu_tai_khoan']
                    );
                }
            } elseif ($payment && $payment['trang_thai'] === 'THANH_CONG') {
                // Đã thanh toán thành công - không có bất kỳ lỗi hoặc cảnh báo nào
                $paymentError = null;
            } elseif ($payment && $payment['trang_thai'] === 'CAN_DOI_SOAT') {
                $paymentError = null; // Hiển thị card đối soát chuyên biệt, không dùng toast lỗi đơn thuần
            } elseif ($payment && in_array($payment['trang_thai'], ['THAT_BAI', 'HET_HAN'], true)) {
                $paymentError = 'Giao dịch đã kết thúc hoặc đã hủy. Không chuyển khoản thêm; trạng thái đặt sân sẽ được cập nhật từ hệ thống.';
            }

            if ($payment) {
                $receiptImage = $this->receipts()->info($payment) ? url('/booking/receipt?id=' . (int) $id) : null;
            }
        } catch (\Throwable $e) {
            $paymentError = 'Không thể đọc cấu hình thanh toán. Vui lòng thử lại hoặc liên hệ cơ sở.';
            error_log('Payment read failed: ' . get_class($e));
        }

        // Only a committed database receipt is evidence of submission; orphan files are not.
        $receiptSubmitted = $payment && $this->receipts()->info($payment) !== null;
        $isReconcile = ($payment && $payment['trang_thai'] === 'CAN_DOI_SOAT');
        $reconcileReason = $isReconcile ? \App\Services\ReceiptImage::stripReceiptTag($payment['ghi_chu'] ?? '') : '';

        // Lấy thông tin cơ sở thể thao (tên cơ sở, hotline, địa chỉ) để khách liên hệ Zalo khi cần đối soát
        $facility = null;
        if (!empty($booking['ma_co_so'])) {
            try {
                $fStmt = $this->app->pdo()->prepare('SELECT * FROM co_so WHERE ma_co_so = :cid');
                $fStmt->execute(['cid' => (int) $booking['ma_co_so']]);
                $facility = $fStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            } catch (\Throwable $e) {}
        }

        // Đảm bảo không hiển thị cảnh báo lỗi nếu đơn đã được xác nhận thành công
        $confirmed = in_array($booking['trang_thai_dat_san'], ['DA_XAC_NHAN', 'HOAN_THANH'], true)
            && ($payment['trang_thai'] ?? '') === 'THANH_CONG';
        if ($confirmed) {
            $paymentError = null;
        } elseif (($payment['trang_thai'] ?? '') === 'THANH_CONG') {
            $paymentError = 'Đã ghi nhận giao dịch nhưng đơn chưa được xác nhận. Không chuyển khoản thêm; vui lòng liên hệ cơ sở để kiểm tra.';
        }

        // Tiêu đề trang tương ứng với trạng thái thực tế của booking
        $pageTitle = 'Chờ thanh toán';
        if ($isReconcile) {
            $pageTitle = 'Cần đối soát thanh toán';
        } elseif ($confirmed) {
            $pageTitle = 'Đặt sân & Thanh toán thành công';
        } elseif ($receiptSubmitted && $booking['trang_thai_dat_san'] === 'CHO_XAC_NHAN') {
            $pageTitle = 'Chờ xác nhận thanh toán';
        } elseif (in_array($booking['trang_thai_dat_san'], ['HET_HAN', 'DA_HUY'], true)) {
            $pageTitle = 'Đã hết thời gian giữ sân';
        } elseif ($receiptSubmitted || $booking['trang_thai_dat_san'] === 'CHO_XAC_NHAN') {
            $pageTitle = 'Đã gửi minh chứng – Chờ xác nhận thanh toán';
        }

        // Lấy thông tin chi tiết khách hàng
        $customer = null;
        if (!empty($user['ma_khach_hang'])) {
            try {
                $stmt = $this->app->pdo()->prepare('SELECT * FROM khach_hang WHERE ma_khach_hang = :cid');
                $stmt->execute(['cid' => (int) $user['ma_khach_hang']]);
                $customer = $stmt->fetch() ?: null;
            } catch (\Throwable) {}
        }

        $isConfirmedBooking = $confirmed && !$isReconcile;
        $isReceiptWait = $receiptSubmitted && !$isReconcile
            && in_array($booking['trang_thai_dat_san'], ['CHO_THANH_TOAN', 'CHO_XAC_NHAN'], true)
            && in_array($payment['trang_thai'] ?? '', ['CHO_THANH_TOAN', 'CHO_XAC_NHAN'], true)
            && ($booking['trang_thai_dat_san'] === 'CHO_XAC_NHAN'
                || $booking['het_han_giu_san_luc'] > $now->format('Y-m-d H:i:s'));
        $showResult = $isConfirmedBooking || $isReceiptWait;
        $this->render($showResult ? 'courts/payment-result' : 'courts/booking-wait', compact(
            'booking', 'now', 'user', 'customer', 'facility', 'bookingBackUrl',
            'payment', 'bankAccount', 'qrUrl', 'paymentError', 'receiptImage',
            'receiptSubmitted', 'receiptNotice', 'isReconcile', 'reconcileReason',
            'isConfirmedBooking', 'isReceiptWait'
        ) + ['title' => $pageTitle]);
    }

    /**
     * TH1: Tiếp nhận ảnh biên lai chuyển khoản từ khách hàng.
     * Bắt buộc phải có ảnh; gửi thành công chỉ là chờ nhân viên kiểm tra, chưa thanh toán thành công.
     */
    public function uploadReceipt(): void
    {
        $user = $this->app->auth()->user();
        if (!$user || $user['ten_vai_tro'] !== 'KHACH_HANG') {
            throw new HttpException(403, 'Vui lòng đăng nhập tài khoản khách hàng.');
        }

        $id = post_string('id');
        if (!\App\Services\CoSoService::validId($id)) {
            throw new HttpException(404, 'Không tìm thấy booking.');
        }

        $bookingId = (int) $id;
        $booking = $this->service()->inspect($bookingId, (int) $user['ma_khach_hang']);
        if (!$booking) {
            throw new HttpException(404, 'Không tìm thấy booking.');
        }

        $relativePath = null;
        try {
            $this->receipts()->upload($bookingId, (int) $user['ma_khach_hang'], $_FILES['bien_lai'] ?? null);
            $_SESSION['booking_receipt_notice'][$bookingId] = 'Đã gửi minh chứng – Chờ xác nhận thanh toán';
            $this->go('/booking/result', 'id', (string)$bookingId);
            return;
        } catch (\App\Services\ValidationException $e) {
            $_SESSION['booking_payment_error'][$bookingId] = $e->errors['bien_lai'] ?? 'Lỗi tải ảnh biên lai.';
        } catch (HttpException $e) {
            $_SESSION['booking_payment_error'][$bookingId] = $e->getMessage();
        } catch (\Throwable $e) {
            $_SESSION['booking_payment_error'][$bookingId] = 'Không thể lưu minh chứng. Vui lòng thử lại hoặc liên hệ cơ sở.';
        }
        $this->go('/booking/wait', 'id', (string)$bookingId);
    }

    public function receipt(): void
    {
        $user = $this->app->auth()->user();
        if (!$user) throw new HttpException(403, 'Vui lòng đăng nhập.');
        $id = $_GET['id'] ?? '';
        if (!is_string($id) || !\App\Services\CoSoService::validId($id)) throw new HttpException(404, 'Không tìm thấy minh chứng.');
        $image = $this->receipts()->authorized((int) $id, $user);
        header('Content-Type: ' . $image['mime']);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Content-Length: ' . filesize($image['absolute']));
        header('Content-Disposition: inline');
        readfile($image['absolute']);
    }

    /**
     * TH2: Khi hết thời gian giữ sân, tự động hủy booking và cập nhật CSDL.
     */
    public function expire(): void
    {
        $json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
        try {
            $user=$this->app->auth()->user();
            if (!$user || $user['ten_vai_tro']!=='KHACH_HANG') throw new HttpException(403,'Vui lòng đăng nhập tài khoản khách hàng.');
            $id=post_string('id');
            if (!\App\Services\CoSoService::validId($id)) throw new HttpException(404,'Không tìm thấy booking.');
            // owned() verifies ownership before expiry; model locks booking then payment.
            $booking=$this->service()->owned((int)$id,(int)$user['ma_khach_hang']);
            if ($json) { $this->json(['success'=>true,'state'=>$booking['trang_thai_dat_san']]); return; }
            $this->go('/booking/wait','id',$id);
        } catch (HttpException $e) {
            if (!$json) throw $e;
            $this->json(['error'=>$e->getMessage()],$e->status);
        }
    }

    private function scheduleUrl(int $facility, string $date): string
    {
        return url('/courts/schedule').'&id='.$facility.'&date='.rawurlencode($date);
    }

    public function cancel(): void
    {
        $json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
        try {
            $user = $this->app->auth()->user();
            if (!$user || $user['ten_vai_tro'] !== 'KHACH_HANG') throw new HttpException(403, 'Vui lòng đăng nhập tài khoản khách hàng.');
            $id = post_string('id');
            if (!\App\Services\CoSoService::validId($id)) throw new HttpException(404, 'Không tìm thấy booking.');
            // Existing Back/navigation requests send only id + CSRF. Leaving is not cancellation.
            // A destructive customer request must explicitly submit action=cancel.
            $explicitCancel = post_string('action') === 'cancel';
            $booking = $explicitCancel
                ? $this->service()->cancelHold((int)$id, (int)$user['ma_khach_hang'])
                : $this->service()->visit((int)$id, (int)$user['ma_khach_hang']);
            $state = $booking['trang_thai_dat_san'];
            // A successful payment with a still-pending booking requires reconciliation, never cancellation.
            if ($explicitCancel && $state === BookingService::PENDING) throw new HttpException(409, 'Booking đã có giao dịch thành công hoặc minh chứng. Không thể hủy giữ; vui lòng tải lại trang.');
            $back = $this->scheduleUrl((int)$booking['ma_co_so'], $booking['lines'][0]['ngay_choi'] ?? CourtScheduleService::now()->format('Y-m-d'));
            if ($explicitCancel && $state === 'DA_HUY') $_SESSION['booking_notice'] = 'Đã hủy giữ sân. Bạn có thể chọn lại khung giờ.';
            if ($json) { $this->json(['state' => $state, 'redirect' => $back]); return; }
            header('Location: '.$back, true, 303);
        } catch (HttpException $error) {
            if (!$json) throw $error;
            $this->json(['error' => $error->getMessage()], $error->status);
        }
    }
}
