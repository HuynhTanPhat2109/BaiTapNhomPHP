<?php

use App\Controllers\AuthController;

$authController = new AuthController($app);
$router->get('/login', [$authController, 'loginForm']);
$router->post('/login', [$authController, 'login']);
$router->get('/register', [$authController, 'registerForm']);
$router->post('/register', [$authController, 'register']);
$router->post('/logout', [$authController, 'logout']);
