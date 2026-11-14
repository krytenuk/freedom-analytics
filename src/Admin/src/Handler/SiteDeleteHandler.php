<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\Model\SiteModel;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Router\RouterInterface;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Deletes a site and everything it collected. Its route must only allow POST. The button sends a token, so another site
 * cannot make a logged-in admin delete one, and the page asks before sending it.
 */
final readonly class SiteDeleteHandler implements RequestHandlerInterface
{
    use RequestDataTrait;

    public const CSRF_KEY = 'site-delete';

    public function __construct(
        private SiteModel $sites,
        private RouterInterface $router,
        private TemplateRendererInterface $template
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $site = $this->sites->find((int) $request->getAttribute('id'));
        if ($site === null) {
            return new HtmlResponse($this->template->render('error::404'), 404);
        }

        $token = $this->getPostData($request)['csrf'] ?? '';
        if ($token === '' || ! $this->getCsrfGuard($request)->validateToken($token, self::CSRF_KEY)) {
            return new RedirectResponse($this->router->generateUri('site', ['id' => $site->getId()]));
        }

        $this->sites->delete($site);

        return new RedirectResponse($this->router->generateUri('sites') . '?notice=deleted');
    }
}
