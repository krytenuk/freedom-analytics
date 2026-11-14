<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\Model\ReportModel;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * One page's numbers for the period, its trend, and the pages visitors came from and went to, like Open Web Analytics'
 * document report. Its route has the page's "id".
 */
final readonly class PageViewHandler implements RequestHandlerInterface
{
    use ReportContextTrait;

    public function __construct(private ReportModel $reports, private TemplateRendererInterface $template)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->getContext($request);
        $page    = $this->reports->findPage($context->site, (int) $request->getAttribute('id'));
        if ($page === null) {
            return new HtmlResponse($this->template->render('error::404'), 404);
        }

        return new HtmlResponse($this->template->render('admin::page', [
            'page'          => $page,
            'summary'       => $this->reports->pageSummary($context->site, $context->period, $page),
            'trend'         => $this->reports->pageTrend($context->site, $context->period, $page),
            'previousPages' => $this->reports->previousPages($context->site, $context->period, $page),
            'nextPages'     => $this->reports->nextPages($context->site, $context->period, $page),
        ]), 200, ['Cache-Control' => 'no-store']);
    }
}
