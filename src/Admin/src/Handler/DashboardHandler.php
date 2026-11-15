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
 * The page people arrive at after logging in, like Open Web Analytics' dashboard: the headline numbers against the
 * period before, the trend, where visitors came from, the top pages and the latest visits.
 */
final readonly class DashboardHandler implements RequestHandlerInterface
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

        return new HtmlResponse($this->template->render('admin::dashboard', [
            'kpis'         => $this->reports->kpis($site, $period),
            'previousKpis' => $this->reports->kpis($site, $period->previous()),
            'trend'        => $this->reports->trend($site, $period),
            'activeNow'    => $this->reports->activeVisitors($site, LiveHandler::ACTIVE_MINUTES),
            'mediums'      => $this->reports->dimension($site, $period, 'medium', 10),
            'devices'      => $this->reports->dimension($site, $period, 'device_type', 10),
            'topPages'     => $this->reports->dimension($site, $period, 'page', 10),
            'referrers'    => $this->reports->dimension($site, $period, 'referrer_host', 8),
            'countries'    => $this->reports->dimension($site, $period, 'country', 8),
            'events'       => $this->reports->dimension($site, $period, 'event_action', 5),
            'latestVisits' => $this->reports->latestVisits($site, $period, 8, 0)['visits'],
        ]), 200, ['Cache-Control' => 'no-store']);
    }
}
