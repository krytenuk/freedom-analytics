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
 * Lists the sites being tracked
 */
final readonly class SiteListHandler implements RequestHandlerInterface
{
    public const NOTICES = ['deleted' => 'The site and all its data have been deleted.'];

    public function __construct(private SiteModel $sites, private TemplateRendererInterface $template)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $sites = $this->sites->findAll();
        $stats = [];
        foreach ($sites as $site) {
            $stats[(int) $site->getId()] = $this->sites->stats($site);
        }

        return new HtmlResponse($this->template->render('admin::sites', [
            'sites'  => $sites,
            'stats'  => $stats,
            'notice' => self::NOTICES[$request->getQueryParams()['notice'] ?? ''] ?? null,
        ]), 200, ['Cache-Control' => 'no-store']);
    }
}
