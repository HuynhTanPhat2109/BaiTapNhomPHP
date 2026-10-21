<?php

namespace App\Controllers;

use App\Services\RegistrationService;
use App\Services\ValidationException;
use App\Services\LoginThrottle;
use Core\Application;
use Core\Controller;
use Core\Session;

final class AuthController extends Controller
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    private function guestOnly(): void
    {
        if (isset($_SESSION['account_id']) && ($user = $this->app->auth()->user())) {
            $this->redirect($this->roleHome($user['ten_vai_tro'] ?? null));
        }
    }

    private function roleHome($role): string
    {
        if (!is_string($role)) {
            return '/account';
        }
        return [
            'QUAN_TRI_TAP_DOAN' => '/admin',
            'KHACH_HANG' => '/',
            'QUAN_LY_CONG_TY' => '/company',
            'QUAN_LY_CO_SO' => '/facility',
            'NHAN_VIEN' => '/staff',
        ][$role] ?? '/account';
    }

    public function loginForm(): void
    {
        $this->guestOnly();
        $message = $_SESSION['notice'] ?? null;
        unset($_SESSION['notice']);
        $this->render('auth/login', ['title' => 'Đăng nhập', 'notice' => $message]);
    }

    public function login(): void
    {
        $this->guestOnly();
        $username = post_string('username');
        $password = post_string('password', false);
        $errors = [];
        $loginError = null;
        if ($username === '') {
            $errors['username'] = 'Vui lòng nhập tên đăng nhập.';
        }
        if ($password === '') {
            $errors['password'] = 'Vui lòng nhập mật khẩu.';
        }
        if (!$errors) {
            // REMOTE_ADDR is server supplied; do not trust X-Forwarded-For.
            $retryAfter = (new LoginThrottle())->consume($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            if ($retryAfter > 0) {
                header('Retry-After: ' . $retryAfter);
                $this->render('auth/login', [
                    'title' => 'Đăng nhập', 'username' => mb_substr($username, 0, 100, 'UTF-8'),
                    'formError' => 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau ' . (int) ceil($retryAfter / 60) . ' phút.',
                ], 429);
                return;
            }
            try {
                if ($this->app->auth()->attempt($username, $password)) {
                    $user = $this->app->auth()->user();
                    $this->redirect($this->roleHome($user['ten_vai_tro'] ?? null));
                }
            } catch (\PDOException $e) {
                $this->connectionError($e);
                $this->render('auth/login', [
                    'title' => 'Đăng nhập', 'username' => mb_substr($username, 0, 100, 'UTF-8'),
                    'formError' => 'Chưa kết nối được cơ sở dữ liệu. Vui lòng thử lại sau.',
                ], 503);
                return;
            }
            $loginError = 'Tên đăng nhập hoặc mật khẩu không chính xác';
        }
        $this->render('auth/login', [
            'title' => 'Đăng nhập',
            'username' => mb_substr($username, 0, 100, 'UTF-8'),
            'errors' => $errors,
            'loginError' => $loginError,
        ], 422);
    }

    public function registerForm(): void
    {
        $this->guestOnly();
        $this->render('auth/register', ['title' => 'Đăng ký khách hàng']);
    }

    public function register(): void
    {
        $this->guestOnly();
        $data = [
            'username' => $_POST['username'] ?? '',
            'name' => $_POST['name'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? '',
            'password_confirmation' => $_POST['password_confirmation'] ?? '',
        ];
        try {
            // Validate before opening a DB connection as well.
            $errors = RegistrationService::validate($data);
            if ($errors) {
                throw new ValidationException($errors);
            }
            (new RegistrationService($this->app->pdo()))->register($data);
            $_SESSION['notice'] = 'Đăng ký thành công. Bạn có thể đăng nhập.';
            $this->redirect('/login');
        } catch (ValidationException $e) {
            unset($data['password'], $data['password_confirmation']);
            foreach ($data as &$value) {
                $value = is_string($value) ? mb_substr(trim($value), 0, 150, 'UTF-8') : '';
            }
            unset($value);
            $this->render('auth/register', ['title' => 'Đăng ký khách hàng', 'errors' => $e->errors, 'old' => $data], 422);
        } catch (\PDOException $e) {
            $this->connectionError($e);
            unset($data['password'], $data['password_confirmation']);
            $this->render('auth/register', [
                'title' => 'Đăng ký khách hàng', 'old' => $data,
                'formError' => 'Chưa kết nối được cơ sở dữ liệu. Vui lòng thử lại sau.',
            ], 503);
        }
    }

    private function connectionError(\PDOException $e): void
    {
        // Only handle connection outages here; SQL/programming errors still reach
        // the global error handler and must not be disguised as validation errors.
        if (!in_array((int) ($e->errorInfo[1] ?? $e->getCode()), [2002, 2003, 2006, 2013], true)) {
            throw $e;
        }
        error_log('Authentication database unavailable; driver code=' . (int) ($e->errorInfo[1] ?? $e->getCode()));
    }

    public function logout(): void
    {
        // CSRF is checked by Router, logout remains available even if DB is down.
        Session::logout();
        $this->redirect('/login');
    }
}
