<?php

namespace App\Controllers;

use App\Services\CustomerProfileService;
use App\Services\PasswordChangeService;
use App\Services\ValidationException;
use Core\Application;
use Core\Controller;
use Core\Session;

final class AccountController extends Controller
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function show(): void
    {
        $notice = $_SESSION['account_notice'] ?? null;
        unset($_SESSION['account_notice']);
        $this->render('auth/account', [
            'title' => 'Tài khoản',
            'user' => $this->app->auth()->user(),
            'notice' => $notice,
        ]);
    }

    public function settings(): void
    {
        $notice = $_SESSION['account_notice'] ?? null;
        unset($_SESSION['account_notice']);
        $this->render('auth/settings', [
            'title' => 'Cài đặt tài khoản',
            'user' => $this->app->auth()->user(),
            'notice' => $notice,
        ]);
    }

    public function updateProfile(): void
    {
        $user = $this->app->auth()->user();
        $data = [
            'name' => $_POST['name'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'birth_date' => $_POST['birth_date'] ?? '',
            'gender' => $_POST['gender'] ?? '',
        ];
        try {
            (new CustomerProfileService($this->app->pdo()))->update((int) $user['ma_tai_khoan'], $data);
            $_SESSION['account_notice'] = 'Thông tin cá nhân đã được cập nhật.';
            $this->redirect('/account');
        } catch (ValidationException $e) {
            foreach ($data as &$value) {
                $value = is_string($value) ? mb_substr(trim($value), 0, 150, 'UTF-8') : '';
            }
            unset($value);
            $this->render('auth/account', [
                'title' => 'Tài khoản', 'user' => $user,
                'profileOld' => $data, 'profileErrors' => $e->errors,
            ], 422);
        }
    }

    public function changePassword(): void
    {
        $user = $this->app->auth()->user();
        try {
            (new PasswordChangeService($this->app->pdo()))->change((int) $user['ma_tai_khoan'], [
                'current_password' => $_POST['current_password'] ?? '',
                'new_password' => $_POST['new_password'] ?? '',
                'new_password_confirmation' => $_POST['new_password_confirmation'] ?? '',
            ]);
            Session::authenticate((int) $user['ma_tai_khoan']);
            $_SESSION['username'] = $user['ten_dang_nhap'];
            $_SESSION['account_notice'] = 'Mật khẩu đã được thay đổi.';
            $this->redirect('/account/settings');
        } catch (ValidationException $e) {
            $this->render('auth/settings', [
                'title' => 'Cài đặt tài khoản', 'user' => $user, 'passwordErrors' => $e->errors,
            ], 422);
        }
    }
}
