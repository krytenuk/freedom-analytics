<?php

declare(strict_types=1);

namespace Admin\Handler;

use FwsDoctrineAuth\Mezzio\SessionLogin;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Router\RouterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Logging out only works from a form with a valid token, so another site cannot log a user out.
 */
final readonly class LogoutHandler implements RequestHandlerInterface
{
    use RequestDataTrait;

    public const CSRF_KEY = 'logout';

    public function __construct(private SessionLogin $sessionLogin, private RouterInterface $router)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $token = $this->getPostData($request)['csrf'] ?? '';

        // An empty token is refused here, as SessionCsrfGuard accepts it when the session has no token
        if ($token === '' || ! $this->getCsrfGuard($request)->validateToken($token, self::CSRF_KEY)) {
            return new RedirectResponse($this->router->generateUri('dashboard'));
        }

        $this->sessionLogin->logout($this->getSession($request));

        return new RedirectResponse($this->router->generateUri('login'));
    }
}
