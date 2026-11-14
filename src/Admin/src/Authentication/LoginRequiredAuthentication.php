<?php

declare(strict_types=1);

namespace Admin\Authentication;

use Mezzio\Authentication\AuthenticationInterface;
use Mezzio\Authentication\UserInterface;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Leaves a note in the session when a request is turned away, so the login page can explain why the user arrived there.
 */
final readonly class LoginRequiredAuthentication implements AuthenticationInterface
{
    public const SESSION_KEY = 'admin-login-required';

    public function __construct(private AuthenticationInterface $authentication)
    {
    }

    public function authenticate(ServerRequestInterface $request): ?UserInterface
    {
        return $this->authentication->authenticate($request);
    }

    public function unauthorizedResponse(ServerRequestInterface $request): ResponseInterface
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        if ($session instanceof SessionInterface) {
            $session->set(self::SESSION_KEY, true);
        }

        return $this->authentication->unauthorizedResponse($request);
    }
}
