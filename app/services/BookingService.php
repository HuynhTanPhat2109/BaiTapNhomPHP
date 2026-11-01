<?php
namespace App\Services;

use App\Models\{Booking, CourtBrowser, San};
use Core\HttpException;
use PDO;
use PDOException;

final class BookingService
{
    public const PENDING = 'CHO_THANH_TOAN';
    public const EXPIRED = 'HET_HAN';
    private Booking $bookings;
    public function __construct(private PDO $pdo) { $this->bookings = new Booking($pdo); }

    public static function selection(array $input): array
    {
        $facility = $input['facility'] ?? null;
        $date = $input['date'] ?? null;
        $slots = $input['slots'] ?? null;
        if (!CoSoService::validId((string) (is_scalar($facility) ? $facility : '')) || !is_string($date)
            || !is_array($slots) || !$slots || count($slots) > 192) throw new HttpException(422, 'Lựa chọn sân không hợp lệ (tối đa 192 ô).');
        $clean = [];
        foreach ($slots as $slot) {
            if (!is_array($slot)) throw new HttpException(422, 'Khung giờ không hợp lệ.');
            $id = $slot['court'] ?? null; $start = $slot['start'] ?? null; $end = $slot['end'] ?? null;
            if (!CoSoService::validId((string) (is_scalar($id) ? $id : '')) || !is_string($start) || !is_string($end)
                || !preg_match('/\A(?:[01][0-9]|2[0-3]):(?:00|30)\z/', $start)
                || !preg_match('/\A(?:(?:[01][0-9]|2[0-3]):(?:00|30)|24:00)\z/', $end)
                || self::minute($end) - self::minute($start) !== 30) throw new HttpException(422, 'Mỗi ô phải đúng 30 phút.');
            $key = (int) $id . '|' . $start;
            if (isset($clean[$key])) throw new HttpException(422, 'Lựa chọn bị trùng ô.');
            $clean[$key] = ['court'=>(int) $id,'start'=>$start,'end'=>$end];
        }
        $clean = array_values($clean);
        usort($clean, static fn($a,$b) => ($a['court'] <=> $b['court']) ?: strcmp($a['start'],$b['start']));
        return ['facility'=>(int) $facility,'date'=>$date,'slots'=>$clean];
    }

    private static function minute(string $time): int { return (int) substr($time,0,2)*60+(int) substr($time,3,2); }

    public static function extras($services, $note): array
    {
        if (!is_array($services) || count($services)>50 || !is_string($note) || !mb_check_encoding($note,'UTF-8') || mb_strlen($note)>500)
            throw new HttpException(422, 'Dịch vụ không hợp lệ hoặc ghi chú vượt 500 ký tự.');
        $clean = [];
        foreach ($services as $id=>$quantity) {
            if (!CoSoService::validId((string)$id) || (!is_int($quantity) && !is_string($quantity))
                || !preg_match('/\A(?:0|[1-9][0-9]{0,5})\z/', (string)$quantity)) throw new HttpException(422, 'Số lượng dịch vụ phải là số nguyên từ 0 đến 999999.');
            if ((int)$quantity > 0) $clean[(int)$id] = (int)$quantity;
        }
        ksort($clean);
        return ['services'=>$clean,'note'=>trim($note)];
    }

    public function quote(array $input, array $extras = ['services'=>[],'note'=>'']): array
    {
        $input = self::selection($input);
        $extras = self::extras($extras['services'] ?? [],$extras['note'] ?? '');
        $now = CourtScheduleService::now();
        CourtScheduleService::validateDate($input['date'],$now);
        $browser = new CourtBrowser($this->pdo);
        $facility = $browser->facilities(['ma_co_so'=>(string)$input['facility']])[0] ?? null;
        if (!$facility) throw new HttpException(409, 'Cơ sở hoặc công ty không còn hoạt động.');
        $schedule = CourtScheduleService::build($facility,$browser->courts($input['facility']),$browser->intervals($input['facility'],$input['date']),$input['date'],$now);
        $available = [];
        foreach ($schedule['rows'] as $row) foreach ($row['cells'] as $cell) if ($cell['state']==='free')
            $available[$row['court']['ma_san'].'|'.substr($cell['start'],0,5).'|'.substr($cell['end'],0,5)] = true;
        $courts=[];
        foreach ($input['slots'] as $slot) {
            if (!isset($available[$slot['court'].'|'.$slot['start'].'|'.$slot['end']])) throw new HttpException(409,'Khung giờ đã qua, vừa được giữ hoặc không còn khả dụng. Vui lòng chọn lại.');
            $courts[$slot['court']] ??= (new San($this->pdo))->find($slot['court']);
        }
        $day = (int)(new \DateTimeImmutable($input['date']))->format('N') >= 6 ? 'CUOI_TUAN' : 'NGAY_THUONG';
        $lines = BookingPricingService::courtLines($input['slots'],$courts,$this->bookings->prices($input['facility'],$input['date'],$day),$input['date']);
        $offers = []; $serviceLines=[];
        foreach ($this->bookings->services($input['facility']) as $service) {
            $id=(int)$service['ma_dich_vu'];
            $service['available'] = $service['so_luong_ton'] === null ? null : max(0,(int)$service['so_luong_ton']-$this->bookings->reserved($id,$now->format('Y-m-d H:i:s')));
            // Rental stock has no checkout/return lifecycle yet; do not promise availability.
            $service['purchasable'] = $service['loai_dich_vu'] !== 'CHO_THUE';
            $offers[$id]=$service;
        }
        foreach ($extras['services'] as $id=>$quantity) {
            $service=$offers[$id] ?? null;
            if (!$service || !$service['purchasable'] || ($service['available'] !== null && $quantity>$service['available']))
                throw new HttpException(409,'Dịch vụ đã ngừng, không thuộc cơ sở hoặc không đủ số lượng khả dụng.');
            $serviceLines[]=['id'=>$id,'name'=>$service['ten_dich_vu'],'unit'=>$service['don_vi_tinh'],'quantity'=>$quantity,
                'rate'=>$service['don_gia'],'amount'=>BookingPricingService::decimal(BookingPricingService::cents($service['don_gia'])*$quantity)];
        }
        $courtTotal = array_sum(array_map(static fn($line)=>BookingPricingService::cents($line['amount']),$lines));
        $serviceTotal = array_sum(array_map(static fn($line)=>BookingPricingService::cents($line['amount']),$serviceLines));
        $result=['facility'=>$facility,'lines'=>$lines,'services'=>$serviceLines,'offers'=>$offers,'minutes'=>count($input['slots'])*30,
            'court_total'=>BookingPricingService::decimal($courtTotal),'service_total'=>BookingPricingService::decimal($serviceTotal),
            'total'=>BookingPricingService::decimal($courtTotal+$serviceTotal)];
        $result['fingerprint']=hash('sha256',json_encode([$lines,$serviceLines],JSON_THROW_ON_ERROR));
        return $result;
    }

    public function confirm(int $customer, string $key, array $input, array $extras, string $acceptedQuote): int
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/',$key)) throw new HttpException(422,'Mã yêu cầu không hợp lệ.');
        $input=self::selection($input); $extras=self::extras($extras['services'] ?? [],$extras['note'] ?? '');
        $hash=hash('sha256',json_encode([$customer,$input,$extras],JSON_THROW_ON_ERROR));
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $this->pdo->beginTransaction();
        try {
            $profile=$this->bookings->lock('khach_hang','ma_khach_hang',$customer);
            if (!$profile) throw new HttpException(403,'Không có hồ sơ khách hàng.');
            $this->bookings->lock('tai_khoan','ma_tai_khoan',(int)$profile['ma_tai_khoan']);
            $profile=$this->bookings->customer($customer);
            if (!$profile || $profile['trang_thai']!=='HOAT_DONG' || $profile['tai_khoan_trang_thai']!=='HOAT_DONG' || $profile['ten_vai_tro']!=='KHACH_HANG') throw new HttpException(403,'Tài khoản không có quyền đặt sân.');
            if (!preg_match('/\A0[0-9]{9}\z/', $profile['so_dien_thoai'] ?? '')) throw new HttpException(422,'Vui lòng cập nhật số điện thoại trong hồ sơ.');
            $existing=$this->bookings->byKey($key);
            if ($existing) {
                if ((int)$existing['ma_khach_hang']!==$customer || !hash_equals($existing['dau_van_tay_yeu_cau'] ?? '',$hash)) throw new HttpException(409,'Mã yêu cầu đã được dùng cho lựa chọn khác.');
                $this->pdo->commit(); return (int)$existing['ma_dat_san'];
            }
            $this->lockScope($input,$extras);
            $quote=$this->quote($input,$extras);
            if (!hash_equals($quote['fingerprint'],$acceptedQuote)) throw new HttpException(409,'Giá hoặc thông tin sân/dịch vụ đã thay đổi. Vui lòng xem lại tổng tiền trước khi xác nhận.');
            $now=CourtScheduleService::now(); $time=$now->format('Y-m-d H:i:s');
            $id=$this->bookings->insert([$key,$hash,$input['facility'],$customer,$time,$time,$now->modify('+6 minutes')->format('Y-m-d H:i:s'),
                $quote['court_total'],$quote['service_total'],$quote['total'],$extras['note']],$quote['lines'],$quote['services']);
            // A slow write must not commit a hold for a slot that started during this transaction.
            $commitTime=CourtScheduleService::now();
            foreach ($input['slots'] as $slot) {
                $start=new \DateTimeImmutable($input['date'].' '.$slot['start'],new \DateTimeZone('Asia/Ho_Chi_Minh'));
                if ($start<=$commitTime) throw new HttpException(409,'Khung giờ vừa bắt đầu. Vui lòng chọn lại lịch.');
            }
            $this->pdo->commit(); return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($e instanceof PDOException && in_array((int)($e->errorInfo[1]??0),[1062,1205,1213],true)) throw new HttpException(409,'Yêu cầu đang được xử lý hoặc lịch vừa thay đổi. Vui lòng thử lại cùng yêu cầu.');
            throw $e;
        }
    }

    private function lockScope(array $input, array $extras): void
    {
        $facility=(new CourtBrowser($this->pdo))->facilities(['ma_co_so'=>(string)$input['facility']])[0] ?? null;
        if (!$facility) throw new HttpException(409,'Cơ sở không khả dụng.');
        $this->bookings->lock('cong_ty_nhuong_quyen','ma_cong_ty',(int)$facility['ma_cong_ty']);
        $locked=$this->bookings->lock('co_so','ma_co_so',$input['facility']);
        if (!$locked || $locked['ma_cong_ty']!=$facility['ma_cong_ty']) throw new HttpException(409,'Cơ sở vừa thay đổi.');
        $courts=[]; $sports=[]; $types=[];
        foreach ($input['slots'] as $slot) {
            $court=(new San($this->pdo))->find($slot['court']);
            if (!$court || (int)$court['ma_co_so']!==$input['facility']) throw new HttpException(409,'Sân không thuộc cơ sở.');
            $courts[$slot['court']]=$court; $sports[(int)$court['ma_bo_mon']]=true; $types[(int)$court['ma_loai_san']]=(int)$court['ma_bo_mon'];
        }
        ksort($sports); ksort($types); ksort($courts);
        foreach ($sports as $id=>$_) $this->bookings->lock('bo_mon','ma_bo_mon',$id);
        foreach ($types as $id=>$sport) {
            $type=$this->bookings->lock('loai_san','ma_loai_san',$id);
            if (!$type || (int)$type['ma_bo_mon']!==$sport) throw new HttpException(409,'Loại sân vừa thay đổi. Vui lòng kiểm tra lại lịch.');
        }
        foreach ($courts as $id=>$before) {
            $row=$this->bookings->lock('san','ma_san',$id);
            if (!$row || $row['ma_loai_san']!=$before['ma_loai_san'] || $row['ma_co_so']!=$input['facility']) throw new HttpException(409,'Sân vừa thay đổi.');
        }
        foreach ($extras['services'] as $id=>$_) $this->bookings->lock('dich_vu','ma_dich_vu',$id);
    }

    public function owned(int $id, int $customer): array
    {
        $row=$this->bookings->find($id);
        if (!$row || (int)$row['ma_khach_hang']!==$customer) throw new HttpException(404,'Không tìm thấy booking.');
        $this->bookings->expire($id);
        return $this->bookings->find($id);
    }

    public function expirePending(): int { return $this->bookings->expire(); }

    public function visit(int $id, int $customer): array
    {
        $booking = $this->owned($id, $customer);
        return $booking + $this->bookings->visit($id);
    }

    public function cancelHold(int $id, int $customer): array
    {
        $this->pdo->beginTransaction();
        try {
            $booking = $this->bookings->find($id, true);
            if (!$booking || (int)$booking['ma_khach_hang'] !== $customer) {
                throw new HttpException(404, 'Không tìm thấy booking.');
            }
            $this->bookings->cancelPending($id);
            $booking = $this->bookings->find($id);
            $this->pdo->commit();
            return $booking + $this->bookings->visit($id);
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /** Integration guard only. Duy must call inside his own transaction, BEFORE any writes.
     * Lock company/facility first, then this booking; guard does not confirm or deduct stock.
     * Recheck immediately before transition; a late callback must go to reconciliation.
     */
    public function lockPayable(int $id): array
    {
        if (!$this->pdo->inTransaction()) throw new \LogicException('Payment guard requires transaction');
        $row=$this->bookings->find($id,true);
        if (!$row || $row['trang_thai_dat_san']!==self::PENDING || $row['het_han_giu_san_luc']<=CourtScheduleService::now()->format('Y-m-d H:i:s'))
            throw new HttpException(409,'Booking hết hạn hoặc không còn chờ thanh toán. Không được xác nhận.');
        return $row;
    }
}
