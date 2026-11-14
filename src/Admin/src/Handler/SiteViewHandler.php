<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\Model\SiteModel;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A site's settings, what it has collected, and its tracking code, like Open Web Analytics' site profile and
 * invocation pages. Its route has the site's "id".
 */
final readonly class SiteViewHandler implements RequestHandlerInterface
{
    use RequestDataTrait;

    public const NOTICES = [
        'added' => 'The site has been added. Put the tracking code below on every page of it.',
        'saved' => 'The site has been saved.',
        'key'   => 'The site has a new key. Update the tracking code on the site, as the old one no longer works.',
    ];

    public function __construct(private SiteModel $sites, private TemplateRendererInterface $template)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $site = $this->sites->find((int) $request->getAttribute('id'));
        if ($site === null) {
            return new HtmlResponse($this->template->render('error::404'), 404);
        }

        $guard = $this->getCsrfGuard($request);

        return new HtmlResponse($this->template->render('admin::site', [
            'site'        => $site,
            'stats'       => $this->sites->stats($site),
            'notice'      => self::NOTICES[$request->getQueryParams()['notice'] ?? ''] ?? null,
            'deleteToken' => $guard->generateToken(SiteDeleteHandler::CSRF_KEY),
            'keyToken'    => $guard->generateToken(SiteKeyHandler::CSRF_KEY),
        ]), 200, ['Cache-Control' => 'no-store']);
    }
}
