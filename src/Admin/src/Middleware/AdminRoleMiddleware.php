<?php

declare(strict_types=1);

namespace Admin\Middleware;

use App\Entity\User;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Keeps the site and user management pages to administrators. Add it after AdminLayoutMiddleware.
 */
final readonly class AdminRoleMiddleware implements MiddlewareInterface
{
    public function __construct(private TemplateRendererInterface $template)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(User::class);
        if ($user instanceof User && $user->isAdmin()) {
            return $handler->handle($request);
        }

        return new HtmlResponse($this->template->render('error::error', [
            'status'  => 403,
            'reason'  => 'Forbidden',
            'message' => 'Only administrators can open this page.',
        ]), 403, ['Cache-Control' => 'no-store']);
    }
}
