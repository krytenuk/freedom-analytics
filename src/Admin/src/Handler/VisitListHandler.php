<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\Model\ReportModel;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function ceil;
use function is_string;
use function max;
use function min;

/**
 * The latest visits of the period, newest first, like Open Web Analytics' latest visits report
 */
final readonly class VisitListHandler implements RequestHandlerInterface
{
    use ReportContextTrait;

    public const PER_PAGE = 25;

    public function __construct(private ReportModel $reports, private TemplateRendererInterface $template)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->getContext($request);
        $query   = $request->getQueryParams();
        $page    = max(1, is_string($query['page'] ?? null) ? (int) $query['page'] : 1);

        $result = $this->reports->latestVisits(
            $context->site,
            $context->period,
            self::PER_PAGE,
            ($page - 1) * self::PER_PAGE
        );
        $pages  = max(1, (int) ceil($result['total'] / self::PER_PAGE));

        return new HtmlResponse($this->template->render('admin::visits', [
            'visits' => $result['visits'],
            'total'  => $result['total'],
            'page'   => min($page, $pages),
            'pages'  => $pages,
        ]), 200, ['Cache-Control' => 'no-store']);
    }
}
