<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\Model\ReportModel;
use Admin\Report\Reports;
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
 * A report of one dimension, from Reports::ALL, as a sortable, paged table with a chart. Its route has the report's
 * "id".
 */
final readonly class ReportHandler implements RequestHandlerInterface
{
    use ReportContextTrait;

    public const PER_PAGE = 25;

    public function __construct(private ReportModel $reports, private TemplateRendererInterface $template)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = (string) $request->getAttribute('id', '');
        if (! isset(Reports::ALL[$id])) {
            return new HtmlResponse($this->template->render('error::404'), 404);
        }

        $context    = $this->getContext($request);
        $report     = Reports::ALL[$id];
        $query      = $request->getQueryParams();
        $sort       = is_string($query['sort'] ?? null) ? $query['sort'] : null;
        $descending = ($query['dir'] ?? 'desc') !== 'asc';
        $page       = max(1, is_string($query['page'] ?? null) ? (int) $query['page'] : 1);

        $result = $this->reports->dimension(
            $context->site,
            $context->period,
            $report['dimension'],
            self::PER_PAGE,
            ($page - 1) * self::PER_PAGE,
            $sort,
            $descending
        );

        $pages = max(1, (int) ceil($result['total'] / self::PER_PAGE));
        if ($page > $pages) {
            $page   = $pages;
            $result = $this->reports->dimension(
                $context->site,
                $context->period,
                $report['dimension'],
                self::PER_PAGE,
                ($page - 1) * self::PER_PAGE,
                $sort,
                $descending
            );
        }

        return new HtmlResponse($this->template->render('admin::report', [
            'id'     => $id,
            'report' => $report,
            'result' => $result,
            'page'   => min($page, $pages),
            'pages'  => $pages,
        ]), 200, ['Cache-Control' => 'no-store']);
    }
}
