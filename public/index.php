<?php

declare(strict_types=1);

use DKAnnual\Auth\Auth;
use DKAnnual\Config;
use DKAnnual\Controller\HomeController;
use DKAnnual\Database;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Http\Router;
use DKAnnual\Middleware\RequireAdminMiddleware;
use DKAnnual\Middleware\VerifyCsrfMiddleware;
use DKAnnual\Repository\UserRepository;
use DKAnnual\Security\Csrf;
use DKAnnual\Session\Session;
use DKAnnual\View\View;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = new Config(dirname(__DIR__) . '/config/config.php');
date_default_timezone_set((string) $config->get('app.timezone', 'Asia/Seoul'));

$session = new Session();
$session->start($config);

$pdo = Database::connect($config);
$users = new UserRepository($pdo);
$auth = new Auth($session, $users);
$csrf = new Csrf($session);
$view = new View(dirname(__DIR__) . '/templates');
$router = new Router();

$home = new HomeController($view, $auth, $csrf);
$verifyCsrf = new VerifyCsrfMiddleware($csrf);
$requireAdmin = new RequireAdminMiddleware($auth);

$router->get('/', [$home, 'index']);
$router->get('/login', static fn (Request $request): Response => Response::html($view->render('login', ['title' => '로그인'])));
$router->get('/health', static function (Request $request) use ($pdo): Response {
    $pdo->query('SELECT 1')->fetchColumn();
    return Response::json(['status' => 'ok']);
});
$router->get('/admin', static fn (Request $request): Response => Response::html($view->render('admin', ['title' => '관리자'])), [$requireAdmin]);
$router->post('/logout', static function (Request $request) use ($auth): Response {
    $auth->logout();
    return Response::redirect('/');
}, [$verifyCsrf]);

$router->dispatch(Request::fromGlobals())->send();
