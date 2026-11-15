<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\Model\ReportModel;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_key_exists;

/**
 * The live page, like Open Web Analytics' real-time view: who is on the site now, where they are, and what they have
 * just done. The page fetches the "live-data" route every few seconds, which returns the same panel as JSON, so the
 * HTML is only ever built here. With "?summary", it returns only the number of active visitors, for the dashboard.
 */
final readonly class LiveHandler implements RequestHandlerInterface
{
    use ReportContextTrait;

    /** How often the page asks for new data */
    public const REFRESH_SECONDS = 10;

    /** Visitors count as on the site now if they did something this recently */
    public const ACTIVE_MINUTES = 5;

    /** How far back the page views chart, counts and activity go */
    public const WINDOW_MINUTES = 30;

    private const HEADERS = ['Cache-Control' => 'no-store'];

    public function __construct(private ReportModel $reports, private TemplateRendererInterface $template)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $site    = $this->getContext($request)->site;
        $route   = $request->getAttribute(RouteResult::class);
        $isData  = $route instanceof RouteResult && $route->getMatchedRouteName() === 'live-data';
        $summary = array_key_exists('summary', $request->getQueryParams());

        if ($isData && $summary) {
            return new JsonResponse(
                ['active' => $this->reports->activeVisitors($site, self::ACTIVE_MINUTES)],
                200,
                self::HEADERS
            );
        }

        $live   = $this->reports->live($site, self::WINDOW_MINUTES, self::ACTIVE_MINUTES);
        $params = [
            'live'          => $live,
            'activeMinutes' => self::ACTIVE_MINUTES,
            'windowMinutes' => self::WINDOW_MINUTES,
        ];

        if ($isData) {
            return new JsonResponse([
                'active'  => $live['active'],
                'updated' => $live['now']->format('H:i:s'),
                'html'    => $this->template->render('admin::partial/live-panel', $params + ['layout' => false]),
            ], 200, self::HEADERS);
        }

        return new HtmlResponse($this->template->render('admin::live', $params + [
            'refreshSeconds' => self::REFRESH_SECONDS,
        ]), 200, self::HEADERS);
    }
}
