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
 * Gives a site a new key, such as when someone has copied its tracking code to another site. Its route must only
 * allow POST.
 */
final readonly class SiteKeyHandler implements RequestHandlerInterface
{
    use RequestDataTrait;

    public const CSRF_KEY = 'site-key';

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

        $url   = $this->router->generateUri('site', ['id' => $site->getId()]);
        $token = $this->getPostData($request)['csrf'] ?? '';
        if ($token === '' || ! $this->getCsrfGuard($request)->validateToken($token, self::CSRF_KEY)) {
            return new RedirectResponse($url);
        }

        $this->sites->regenerateKey($site);

        return new RedirectResponse($url . '?notice=key');
    }
}
