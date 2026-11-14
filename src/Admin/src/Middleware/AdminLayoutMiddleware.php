<?php

declare(strict_types=1);

namespace Admin\Middleware;

use Admin\Handler\LogoutHandler;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use FwsDoctrineAuth\Mezzio\SessionLogin;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Csrf\CsrfGuardInterface;
use Mezzio\Csrf\CsrfMiddleware;
use Mezzio\Router\RouterInterface;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * Renders the page in the NiceAdmin layout, and puts the logged-in User entity on the request, under User::class.
 * Gives the layout the user, and the token for its Sign Out button. Add it to every logged-in route, after
 * AuthenticationMiddleware.
 */
final readonly class AdminLayoutMiddleware implements MiddlewareInterface
{
    public const LAYOUT = 'layout::admin';

    public function __construct(
        private TemplateRendererInterface $template,
        private EntityManagerInterface $entityManager,
        private RouterInterface $router,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $request->getAttribute(UserInterface::class) instanceof UserInterface) {
            throw new RuntimeException('AuthenticationMiddleware must run before ' . self::class);
        }

        $guard = $request->getAttribute(CsrfMiddleware::GUARD_ATTRIBUTE);
        if (! $guard instanceof CsrfGuardInterface) {
            throw new RuntimeException('CsrfMiddleware must run before ' . self::class);
        }

        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        $userId  = $session instanceof SessionInterface ? $session->get(SessionLogin::SESSION_KEY) : null;
        $user    = $userId === null ? null : $this->entityManager->find(User::class, (int) $userId);
        if (! $user instanceof User) {
            return new RedirectResponse($this->router->generateUri('login'));
        }

        $all = TemplateRendererInterface::TEMPLATE_ALL;
        $this->template->addDefaultParam($all, 'layout', self::LAYOUT);
        $this->template->addDefaultParam($all, 'currentUser', $user);
        $this->template->addDefaultParam($all, 'logoutToken', $guard->generateToken(LogoutHandler::CSRF_KEY));

        return $handler->handle($request->withAttribute(User::class, $user));
    }
}
