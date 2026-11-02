<?php
namespace App\Controllers;

use App\Services\{BookingService, CourtScheduleService};
use Core\{Application, Controller, HttpException};

final class BookingController extends Controller
{
    public function __construct(private Application $app) {}
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
            try {
                $paymentService = new \App\Services\PaymentService($this->app->pdo());
                $payment = $paymentService->getPaymentByBooking((int) $id);
                $paymentStatus = $payment['trang_thai'] ?? null;
            } catch (\Throwable $e) {}
            $this->json([
                'state' => $booking['trang_thai_dat_san'],
                'payment_status' => $paymentStatus,
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
        try {
            $paymentService = new \App\Services\PaymentService($this->app->pdo());
            $payment = $paymentService->getPaymentByBooking((int) $id);
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
            } elseif ($payment) {
                $paymentError = 'Giao dịch đã kết thúc hoặc đang cần kiểm tra. Không chuyển khoản thêm; trạng thái đặt sân sẽ được cập nhật từ hệ thống.';
            }
        } catch (\Throwable $e) {
            $paymentError = 'Không thể đọc cấu hình thanh toán. Vui lòng thử lại hoặc liên hệ cơ sở.';
            error_log('Payment read failed: ' . get_class($e));
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

        $this->render('courts/booking-wait',compact('booking','now','user','customer','bookingBackUrl','payment','bankAccount','qrUrl','paymentError')+['title'=>'Chờ thanh toán']);
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
            $booking = $this->service()->cancelHold((int)$id, (int)$user['ma_khach_hang']);
            $state = $booking['trang_thai_dat_san'];
            // A successful payment with a still-pending booking requires reconciliation, never cancellation.
            if ($state === BookingService::PENDING) throw new HttpException(409, 'Booking đã có giao dịch thành công. Không thể hủy giữ; vui lòng tải lại trang.');
            $back = $this->scheduleUrl((int)$booking['ma_co_so'], $booking['lines'][0]['ngay_choi'] ?? CourtScheduleService::now()->format('Y-m-d'));
            if ($state === 'DA_HUY') $_SESSION['booking_notice'] = 'Đã hủy giữ sân. Bạn có thể chọn lại khung giờ.';
            if ($json) { $this->json(['state' => $state, 'redirect' => $back]); return; }
            header('Location: '.$back, true, 303);
        } catch (HttpException $error) {
            if (!$json) throw $error;
            $this->json(['error' => $error->getMessage()], $error->status);
        }
    }
}
