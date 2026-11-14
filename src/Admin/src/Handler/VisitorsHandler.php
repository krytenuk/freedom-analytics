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
 * The visitors overview, like Open Web Analytics' visitors report: unique, new and returning visitors over the period,
 * with their browsers, devices and countries.
 */
final readonly class VisitorsHandler implements RequestHandlerInterface
{
    use ReportContextTrait;

    public function __construct(private ReportModel $reports, private TemplateRendererInterface $template)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->getContext($request);
        $site    = $context->site;
        $period  = $context->period;

        return new HtmlResponse($this->template->render('admin::visitors', [
            'kpis'         => $this->reports->kpis($site, $period),
            'previousKpis' => $this->reports->kpis($site, $period->previous()),
            'trend'        => $this->reports->trend($site, $period),
            'browsers'     => $this->reports->dimension($site, $period, 'browser', 6),
            'systems'      => $this->reports->dimension($site, $period, 'os', 6),
            'devices'      => $this->reports->dimension($site, $period, 'device_type', 6),
            'countries'    => $this->reports->dimension($site, $period, 'country', 10),
            'loyalty'      => $this->reports->dimension($site, $period, 'visit_number'),
        ]), 200, ['Cache-Control' => 'no-store']);
    }
}
