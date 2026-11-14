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
 * One visit: where the visitor came from, their browser, and every page view and event in order, like Open Web
 * Analytics' visit detail ("clickstream"). Its route has the visit's "id".
 */
final readonly class VisitViewHandler implements RequestHandlerInterface
{
    use ReportContextTrait;

    public function __construct(private ReportModel $reports, private TemplateRendererInterface $template)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->getContext($request);
        $visit   = $this->reports->findVisit($context->site, (int) $request->getAttribute('id'));
        if ($visit === null) {
            return new HtmlResponse($this->template->render('error::404'), 404);
        }

        return new HtmlResponse($this->template->render('admin::visit', [
            'visit'    => $visit,
            'timeline' => $this->reports->visitTimeline($visit),
            'history'  => $this->reports->visitorHistory($visit),
            'country'  => $visit->getCountry() === null ? null : $this->reports->countryName($visit->getCountry()),
        ]), 200, ['Cache-Control' => 'no-store']);
    }
}
