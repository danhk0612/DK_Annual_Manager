<?php

declare(strict_types=1);

use DKAnnual\Auth\Auth;
use DKAnnual\Config;
use DKAnnual\Controller\AdminAnnualLeaveController;
use DKAnnual\Controller\AdminUserController;
use DKAnnual\Controller\CalendarController;
use DKAnnual\Controller\HomeController;
use DKAnnual\Controller\LeaveController;
use DKAnnual\Controller\ProfileController;
use DKAnnual\Controller\TelegramAuthController;
use DKAnnual\Database;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Http\Router;
use DKAnnual\Leave\AnnualLeaveCalculator;
use DKAnnual\Leave\AnnualLeaveService;
use DKAnnual\Leave\LeaveDateCalculator;
use DKAnnual\Middleware\RequireAdminMiddleware;
use DKAnnual\Middleware\RequireAuthMiddleware;
use DKAnnual\Middleware\VerifyCsrfMiddleware;
use DKAnnual\Repository\AnnualLeaveLedgerRepository;
use DKAnnual\Repository\HolidayRepository;
use DKAnnual\Repository\LeaveRequestRepository;
use DKAnnual\Repository\LeaveTypeRepository;
use DKAnnual\Repository\UserRepository;
use DKAnnual\Security\Csrf;
use DKAnnual\Session\Session;
use DKAnnual\Telegram\TelegramOidcClient;
use DKAnnual\View\View;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = new Config(dirname(__DIR__) . '/config/config.php');
date_default_timezone_set((string) $config->get('app.timezone', 'Asia/Seoul'));

$session = new Session();
$session->start($config);

$pdo = Database::connect($config);
$users = new UserRepository($pdo);
$ledger = new AnnualLeaveLedgerRepository($pdo);
$leaveTypes = new LeaveTypeRepository($pdo);
$holidays = new HolidayRepository($pdo);
$leaveRequests = new LeaveRequestRepository($pdo);
$auth = new Auth($session, $users);
$csrf = new Csrf($session);
$view = new View(dirname(__DIR__) . '/templates');
$router = new Router();

$annualLeave = new AnnualLeaveService(new AnnualLeaveCalculator(), $ledger);
$home = new HomeController($view, $auth, $csrf);
$telegramAuth = new TelegramAuthController(
    $config,
    $session,
    new TelegramOidcClient($config),
    $users,
    $auth,
    $view,
);
$adminUsers = new AdminUserController($users, $view, $csrf);
$adminAnnualLeave = new AdminAnnualLeaveController($users, $ledger, $annualLeave, $auth, $view, $csrf);
$profile = new ProfileController($auth, $users, $view, $csrf);
$leave = new LeaveController(
    $auth,
    $leaveTypes,
    $holidays,
    $leaveRequests,
    new LeaveDateCalculator(),
    $view,
    $csrf,
);
$calendar = new CalendarController($leaveRequests, $holidays, $view);

$verifyCsrf = new VerifyCsrfMiddleware($csrf);
$requireAuth = new RequireAuthMiddleware($auth);
$requireAdmin = new RequireAdminMiddleware($auth);

$router->get('/', [$home, 'index']);
$router->get('/login', [$telegramAuth, 'loginPage']);
$router->get('/auth/telegram/start', [$telegramAuth, 'start']);
$router->get('/auth/telegram/callback', [$telegramAuth, 'callback']);
$router->get('/health', static function (Request $request) use ($pdo): Response {
    $pdo->query('SELECT 1')->fetchColumn();
    return Response::json(['status' => 'ok']);
});

$router->get('/calendar', [$calendar, 'index'], [$requireAuth]);
$router->get('/leave', [$leave, 'index'], [$requireAuth]);
$router->post('/leave/create', [$leave, 'create'], [$requireAuth, $verifyCsrf]);
$router->post('/leave/cancel', [$leave, 'cancel'], [$requireAuth, $verifyCsrf]);

$router->get('/profile', [$profile, 'index'], [$requireAuth]);
$router->post('/profile/hire-date', [$profile, 'saveHireDate'], [$requireAuth, $verifyCsrf]);

$router->get('/admin', static fn (Request $request): Response => Response::html($view->render('admin', ['title' => '관리자'])), [$requireAdmin]);
$router->get('/admin/users', [$adminUsers, 'index'], [$requireAdmin]);
$router->post('/admin/users/save', [$adminUsers, 'save'], [$requireAdmin, $verifyCsrf]);
$router->get('/admin/annual-leave', [$adminAnnualLeave, 'index'], [$requireAdmin]);
$router->post('/admin/annual-leave/sync', [$adminAnnualLeave, 'sync'], [$requireAdmin, $verifyCsrf]);
$router->post('/admin/annual-leave/adjust', [$adminAnnualLeave, 'adjust'], [$requireAdmin, $verifyCsrf]);

$router->post('/logout', static function (Request $request) use ($auth): Response {
    $auth->logout();
    return Response::redirect('/');
}, [$verifyCsrf]);

$router->dispatch(Request::fromGlobals())->send();
