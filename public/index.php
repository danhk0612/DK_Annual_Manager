<?php

declare(strict_types=1);

use DKAnnual\Auth\Auth;
use DKAnnual\Config;
use DKAnnual\Controller\AdminAnnualLeaveController;
use DKAnnual\Controller\AdminAuditController;
use DKAnnual\Controller\AdminDashboardController;
use DKAnnual\Controller\AdminHolidayController;
use DKAnnual\Controller\AdminLeaveRequestController;
use DKAnnual\Controller\AdminReportController;
use DKAnnual\Controller\AdminUserController;
use DKAnnual\Controller\CalendarController;
use DKAnnual\Controller\LeaveController;
use DKAnnual\Controller\ProfileController;
use DKAnnual\Controller\TelegramAuthController;
use DKAnnual\Database;
use DKAnnual\Error\ErrorHandler;
use DKAnnual\Holiday\KasiHolidayClient;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Http\Router;
use DKAnnual\Leave\AnnualLeaveCalculator;
use DKAnnual\Leave\AnnualLeaveService;
use DKAnnual\Leave\LeaveDateCalculator;
use DKAnnual\Leave\LeaveReviewService;
use DKAnnual\Middleware\RequireAdminMiddleware;
use DKAnnual\Middleware\RequireAuthMiddleware;
use DKAnnual\Middleware\VerifyCsrfMiddleware;
use DKAnnual\Repository\AnnualLeaveLedgerRepository;
use DKAnnual\Repository\AuditLogRepository;
use DKAnnual\Repository\HolidayRepository;
use DKAnnual\Repository\LeaveRequestRepository;
use DKAnnual\Repository\LeaveTypeRepository;
use DKAnnual\Repository\ReportingRepository;
use DKAnnual\Repository\UserRepository;
use DKAnnual\Security\Csrf;
use DKAnnual\Session\Session;
use DKAnnual\Telegram\LeaveNotificationService;
use DKAnnual\Telegram\TelegramBotClient;
use DKAnnual\Telegram\TelegramOidcClient;
use DKAnnual\View\View;

require dirname(__DIR__) . '/vendor/autoload.php';

ErrorHandler::register();

$config = new Config(dirname(__DIR__) . '/config/config.php');
ErrorHandler::setDebug((bool) $config->get('app.debug', false));
date_default_timezone_set((string) $config->get('app.timezone', 'Asia/Seoul'));

$session = new Session();
$session->start($config);

$pdo = Database::connect($config);
$users = new UserRepository($pdo);
$ledger = new AnnualLeaveLedgerRepository($pdo);
$leaveTypes = new LeaveTypeRepository($pdo);
$holidays = new HolidayRepository($pdo);
$leaveRequests = new LeaveRequestRepository($pdo);
$audit = new AuditLogRepository($pdo);
$reports = new ReportingRepository($pdo);
$auth = new Auth($session, $users);
$csrf = new Csrf($session);
$view = new View(dirname(__DIR__) . '/templates');
$router = new Router();

$notifications = new LeaveNotificationService($config, new TelegramBotClient($config), $users);
$annualLeave = new AnnualLeaveService(new AnnualLeaveCalculator(), $ledger);
$telegramAuth = new TelegramAuthController(
    $config,
    $session,
    new TelegramOidcClient($config),
    $users,
    $auth,
    $view,
);
$adminDashboard = new AdminDashboardController($reports, $audit, $view);
$adminReports = new AdminReportController($reports, $view);
$adminAudit = new AdminAuditController($audit, $view);
$adminUsers = new AdminUserController($users, $annualLeave, $auth, $audit, $view, $csrf);
$adminAnnualLeave = new AdminAnnualLeaveController($users, $ledger, $annualLeave, $auth, $audit, $view, $csrf);
$adminRequests = new AdminLeaveRequestController(
    $leaveRequests,
    new LeaveReviewService($pdo),
    $notifications,
    $auth,
    $audit,
    $view,
    $csrf,
);
$adminHolidays = new AdminHolidayController(
    $holidays,
    new KasiHolidayClient($config),
    $auth,
    $audit,
    $view,
    $csrf,
);
$profile = new ProfileController($auth, $users, $annualLeave, $audit, $view, $csrf);
$leave = new LeaveController(
    $auth,
    $leaveTypes,
    $holidays,
    $leaveRequests,
    $annualLeave,
    $ledger,
    new LeaveDateCalculator(),
    $notifications,
    $audit,
    $view,
    $csrf,
);
$calendar = new CalendarController($auth, $leaveRequests, $holidays, $annualLeave, $view);

$verifyCsrf = new VerifyCsrfMiddleware($csrf);
$requireAuth = new RequireAuthMiddleware($auth);
$requireAdmin = new RequireAdminMiddleware($auth);

$router->get('/', [$calendar, 'index'], [$requireAuth]);
$router->get('/login', [$telegramAuth, 'loginPage']);
$router->get('/auth/telegram/start', [$telegramAuth, 'start']);
$router->get('/auth/telegram/callback', [$telegramAuth, 'callback']);
$router->get('/health', static function (Request $request) use ($pdo): Response {
    $pdo->query('SELECT 1')->fetchColumn();
    return Response::json(['status' => 'ok']);
});

$router->get('/calendar', [$calendar, 'index'], [$requireAuth]);
$router->get('/leave', [$leave, 'index'], [$requireAuth]);
$router->get('/leave/history', [$leave, 'history'], [$requireAuth]);
$router->post('/leave/create', [$leave, 'create'], [$requireAuth, $verifyCsrf]);
$router->post('/leave/cancel', [$leave, 'cancel'], [$requireAuth, $verifyCsrf]);

$router->get('/profile', [$profile, 'index'], [$requireAuth]);
$router->post('/profile/save', [$profile, 'saveProfile'], [$requireAuth, $verifyCsrf]);
$router->post('/profile/hire-date', [$profile, 'saveProfile'], [$requireAuth, $verifyCsrf]);

$router->get('/admin', [$adminDashboard, 'index'], [$requireAdmin]);
$router->get('/admin/reports', [$adminReports, 'index'], [$requireAdmin]);
$router->get('/admin/audit', [$adminAudit, 'index'], [$requireAdmin]);
$router->get('/admin/requests', [$adminRequests, 'index'], [$requireAdmin]);
$router->post('/admin/requests/review', [$adminRequests, 'review'], [$requireAdmin, $verifyCsrf]);
$router->get('/admin/users', [$adminUsers, 'index'], [$requireAdmin]);
$router->post('/admin/users/save', [$adminUsers, 'save'], [$requireAdmin, $verifyCsrf]);
$router->get('/admin/annual-leave', [$adminAnnualLeave, 'index'], [$requireAdmin]);
$router->post('/admin/annual-leave/sync', [$adminAnnualLeave, 'sync'], [$requireAdmin, $verifyCsrf]);
$router->post('/admin/annual-leave/adjust', [$adminAnnualLeave, 'adjust'], [$requireAdmin, $verifyCsrf]);
$router->post('/admin/annual-leave/set-total', [$adminAnnualLeave, 'setTotal'], [$requireAdmin, $verifyCsrf]);
$router->get('/admin/holidays', [$adminHolidays, 'index'], [$requireAdmin]);
$router->post('/admin/holidays/sync', [$adminHolidays, 'sync'], [$requireAdmin, $verifyCsrf]);
$router->post('/admin/holidays/save', [$adminHolidays, 'save'], [$requireAdmin, $verifyCsrf]);
$router->post('/admin/holidays/delete', [$adminHolidays, 'delete'], [$requireAdmin, $verifyCsrf]);

$router->post('/logout', static function (Request $request) use ($auth): Response {
    $auth->logout();
    return Response::redirect('/');
}, [$verifyCsrf]);

$router->dispatch(Request::fromGlobals())->send();
