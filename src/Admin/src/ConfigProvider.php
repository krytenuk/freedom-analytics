<?php

declare(strict_types=1);

namespace Admin;

use Admin\Authentication\LoginRequiredAuthentication;
use Admin\Forms\LoginForm;
use Admin\Forms\ProfileForm;
use Admin\Forms\SiteForm;
use Admin\Forms\TwoFactorCodeForm;
use Admin\Forms\UserForm;
use Admin\Handler\DashboardHandler;
use Admin\Handler\LiveHandler;
use Admin\Handler\LoginHandler;
use Admin\Handler\LogoutHandler;
use Admin\Handler\PageViewHandler;
use Admin\Handler\ProfileHandler;
use Admin\Handler\ReportHandler;
use Admin\Handler\SiteDeleteHandler;
use Admin\Handler\SiteFormHandler;
use Admin\Handler\SiteKeyHandler;
use Admin\Handler\SiteListHandler;
use Admin\Handler\SiteViewHandler;
use Admin\Handler\TwoFactorLoginHandler;
use Admin\Handler\TwoFactorSettingsHandler;
use Admin\Handler\UserDeleteHandler;
use Admin\Handler\UserFormHandler;
use Admin\Handler\UserListHandler;
use Admin\Handler\VisitListHandler;
use Admin\Handler\VisitorsHandler;
use Admin\Handler\VisitViewHandler;
use Admin\Middleware\AdminLayoutMiddleware;
use Admin\Middleware\AdminRoleMiddleware;
use Admin\Middleware\ReportContextMiddleware;
use Admin\Model\ReportModel;
use Admin\Model\SiteModel;
use Admin\Model\UserModel;
use App\Tracking\TrackingConfig;
use Doctrine\ORM\EntityManagerInterface;
use FwsDoctrineAuth\AppTwoFactor\AuthenticatorAppMethod;
use FwsDoctrineAuth\AppTwoFactor\AuthenticatorAppService;
use FwsDoctrineAuth\AppTwoFactor\QrCode;
use FwsDoctrineAuth\Login\LoginService;
use FwsDoctrineAuth\Mezzio\SessionAuthentication;
use FwsDoctrineAuth\Mezzio\SessionLogin;
use FwsDoctrineAuth\Mezzio\TwoFactorLogin;
use FwsDoctrineAuth\Password\PasswordHasherInterface;
use FwsDoctrineAuth\Throttle\LoginThrottleInterface;
use FwsDoctrineAuth\User\UserProviderInterface;
use Laminas\Form\FormElementManager;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Mezzio\Authentication\AuthenticationInterface;
use Mezzio\Authentication\AuthenticationMiddleware;
use Mezzio\Router\RouterInterface;
use Mezzio\Template\TemplateRendererInterface;

use function array_keys;
use function array_map;

/**
 * The reporting interface, in the NiceAdmin layout: login with optional two factor authentication, the dashboard and
 * reports, and, for administrators, the sites and users.
 *
 * @psalm-api
 */
final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies'               => $this->getDependencies(),
            'templates'                  => $this->getTemplates(),
            'form_elements'              => $this->getFormElements(),
            'routes'                     => $this->getRoutes(),
            ConfigAbstractFactory::class => $this->getConfigAbstractFactoryConfig(),
        ];
    }

    public function getDependencies(): array
    {
        $factories = [];
        foreach (array_keys($this->getConfigAbstractFactoryConfig()) as $service) {
            $factories[$service] = ConfigAbstractFactory::class;
        }

        return [
            'aliases'   => [
                // Replaces doctrine-auth's alias, so every route using AuthenticationMiddleware sets the login page
                // message
                AuthenticationInterface::class => LoginRequiredAuthentication::class,
            ],
            'factories' => $factories,
        ];
    }

    public function getConfigAbstractFactoryConfig(): array
    {
        $reportHandler = [ReportModel::class, TemplateRendererInterface::class];

        return [
            LoginRequiredAuthentication::class => [SessionAuthentication::class],
            AdminLayoutMiddleware::class       => [
                TemplateRendererInterface::class,
                EntityManagerInterface::class,
                RouterInterface::class,
            ],
            AdminRoleMiddleware::class         => [TemplateRendererInterface::class],
            ReportContextMiddleware::class     => [
                SiteModel::class,
                TrackingConfig::class,
                TemplateRendererInterface::class,
            ],
            ReportModel::class                 => [EntityManagerInterface::class, TrackingConfig::class],
            SiteModel::class                   => [EntityManagerInterface::class],
            UserModel::class                   => [EntityManagerInterface::class, PasswordHasherInterface::class],
            LoginHandler::class                => [
                FormElementManager::class,
                LoginService::class,
                SessionLogin::class,
                TwoFactorLogin::class,
                AuthenticationInterface::class,
                RouterInterface::class,
                TemplateRendererInterface::class,
            ],
            TwoFactorLoginHandler::class       => [
                FormElementManager::class,
                TwoFactorLogin::class,
                AuthenticationInterface::class,
                RouterInterface::class,
                TemplateRendererInterface::class,
            ],
            LogoutHandler::class               => [SessionLogin::class, RouterInterface::class],
            TwoFactorSettingsHandler::class    => [
                FormElementManager::class,
                AuthenticatorAppService::class,
                AuthenticatorAppMethod::class,
                QrCode::class,
                UserProviderInterface::class,
                LoginThrottleInterface::class,
                RouterInterface::class,
                TemplateRendererInterface::class,
            ],
            ProfileHandler::class              => [
                FormElementManager::class,
                UserModel::class,
                SessionLogin::class,
                RouterInterface::class,
                TemplateRendererInterface::class,
            ],
            DashboardHandler::class            => $reportHandler,
            LiveHandler::class                 => $reportHandler,
            VisitorsHandler::class             => $reportHandler,
            ReportHandler::class               => $reportHandler,
            VisitListHandler::class            => $reportHandler,
            VisitViewHandler::class            => $reportHandler,
            PageViewHandler::class             => $reportHandler,
            SiteListHandler::class             => [SiteModel::class, TemplateRendererInterface::class],
            SiteViewHandler::class             => [SiteModel::class, TemplateRendererInterface::class],
            SiteFormHandler::class             => [
                FormElementManager::class,
                SiteModel::class,
                RouterInterface::class,
                TemplateRendererInterface::class,
            ],
            SiteDeleteHandler::class           => [
                SiteModel::class,
                RouterInterface::class,
                TemplateRendererInterface::class,
            ],
            SiteKeyHandler::class              => [
                SiteModel::class,
                RouterInterface::class,
                TemplateRendererInterface::class,
            ],
            UserListHandler::class             => [UserModel::class, TemplateRendererInterface::class],
            UserFormHandler::class             => [
                FormElementManager::class,
                UserModel::class,
                SiteModel::class,
                RouterInterface::class,
                TemplateRendererInterface::class,
            ],
            UserDeleteHandler::class           => [
                UserModel::class,
                RouterInterface::class,
                TemplateRendererInterface::class,
            ],
        ];
    }

    public function getFormElements(): array
    {
        return [
            'factories' => [
                LoginForm::class         => InvokableFactory::class,
                ProfileForm::class       => InvokableFactory::class,
                SiteForm::class          => InvokableFactory::class,
                TwoFactorCodeForm::class => InvokableFactory::class,
                UserForm::class          => InvokableFactory::class,
            ],
        ];
    }

    public function getTemplates(): array
    {
        return [
            // Pages before logging in, and error pages, use the plain layout. AdminLayoutMiddleware switches logged-in
            // pages to layout::admin.
            'layout' => 'layout::auth',
            'paths'  => [
                'admin'  => [__DIR__ . '/../templates'],
                'layout' => [__DIR__ . '/../templates/layout'],
            ],
        ];
    }

    public function getRoutes(): array
    {
        $loggedIn = static fn (string $handler): array => [
            AuthenticationMiddleware::class,
            AdminLayoutMiddleware::class,
            $handler,
        ];
        $report   = static fn (string $handler): array => [
            AuthenticationMiddleware::class,
            AdminLayoutMiddleware::class,
            ReportContextMiddleware::class,
            $handler,
        ];
        $admin    = static fn (string $handler): array => [
            AuthenticationMiddleware::class,
            AdminLayoutMiddleware::class,
            AdminRoleMiddleware::class,
            $handler,
        ];

        $routes = [
            // Logging in and out
            ['/login', LoginHandler::class, ['GET', 'POST'], 'login'],
            ['/login/code', TwoFactorLoginHandler::class, ['GET', 'POST'], 'login-code'],
            ['/logout', LogoutHandler::class, ['POST'], 'logout'],
            // Reports
            ['/', $report(DashboardHandler::class), ['GET'], 'dashboard'],
            ['/live', $report(LiveHandler::class), ['GET'], 'live'],
            ['/live/data', $report(LiveHandler::class), ['GET'], 'live-data'],
            ['/visitors', $report(VisitorsHandler::class), ['GET'], 'visitors'],
            ['/visits', $report(VisitListHandler::class), ['GET'], 'visits'],
            ['/visits/{id:\d+}', $report(VisitViewHandler::class), ['GET'], 'visit'],
            ['/pages/{id:\d+}', $report(PageViewHandler::class), ['GET'], 'page'],
            ['/reports/{id:[a-z-]+}', $report(ReportHandler::class), ['GET'], 'report'],
            // The logged-in user's account
            ['/profile', $loggedIn(ProfileHandler::class), ['GET', 'POST'], 'profile'],
            ['/two-factor', $loggedIn(TwoFactorSettingsHandler::class), ['GET', 'POST'], 'two-factor'],
            // Administration
            ['/sites', $admin(SiteListHandler::class), ['GET'], 'sites'],
            ['/sites/add', $admin(SiteFormHandler::class), ['GET', 'POST'], 'site-add'],
            ['/sites/{id:\d+}', $admin(SiteViewHandler::class), ['GET'], 'site'],
            ['/sites/{id:\d+}/edit', $admin(SiteFormHandler::class), ['GET', 'POST'], 'site-edit'],
            ['/sites/{id:\d+}/key', $admin(SiteKeyHandler::class), ['POST'], 'site-key'],
            ['/sites/{id:\d+}/delete', $admin(SiteDeleteHandler::class), ['POST'], 'site-delete'],
            ['/users', $admin(UserListHandler::class), ['GET'], 'users'],
            ['/users/add', $admin(UserFormHandler::class), ['GET', 'POST'], 'user-add'],
            ['/users/{id:\d+}/edit', $admin(UserFormHandler::class), ['GET', 'POST'], 'user-edit'],
            ['/users/{id:\d+}/delete', $admin(UserDeleteHandler::class), ['POST'], 'user-delete'],
        ];

        return array_map(static fn (array $route): array => [
            'path'            => $route[0],
            'middleware'      => $route[1],
            'allowed_methods' => $route[2],
            'name'            => $route[3],
        ], $routes);
    }
}
