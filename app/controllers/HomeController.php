<?php

namespace App\Controllers;

use Core\Application;
use Core\Controller;

final class HomeController extends Controller
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function index(): void
    {
        $this->render('home', ['title' => 'Hệ thống quản lý sân thể thao']);
    }

    public function rolePage(string $title): void
    {
        $user = $this->app->auth()->user();
        $stats = ['bookings' => 0, 'invoices' => 0];
        if (!empty($user['ma_co_so'])) {
            try {
                $pdo = $this->app->pdo();
                $bStmt = $pdo->prepare('SELECT COUNT(*) FROM dat_san WHERE ma_co_so = :co_so');
                $bStmt->execute(['co_so' => (int) $user['ma_co_so']]);
                $stats['bookings'] = (int) $bStmt->fetchColumn();

                $invStmt = $pdo->prepare('SELECT COUNT(*) FROM hoa_don hd JOIN dat_san ds ON ds.ma_dat_san = hd.ma_dat_san WHERE ds.ma_co_so = :co_so');
                $invStmt->execute(['co_so' => (int) $user['ma_co_so']]);
                $stats['invoices'] = (int) $invStmt->fetchColumn();
            } catch (\Throwable $e) {
                // Non-critical stat lookup fallback
            }
        }
        $this->render('auth/role', [
            'title' => $title,
            'user' => $user,
            'stats' => $stats,
        ]);
    }
}
