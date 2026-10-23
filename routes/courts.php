<?php
$courtBrowser = new App\Controllers\CourtBrowserController($app);
$router->get('/courts', [$courtBrowser, 'index']);
$router->get('/courts/show', [$courtBrowser, 'show']);
$router->get('/courts/panel', [$courtBrowser, 'panel']);
$router->get('/courts/schedule', [$courtBrowser, 'schedule']);
