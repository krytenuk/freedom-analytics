<?php

declare(strict_types=1);

namespace Admin\Middleware;

use Admin\Model\SiteModel;
use Admin\Report\Period;
use Admin\Report\ReportContext;
use App\Entity\User;
use App\Tracking\TrackingConfig;
use DateTimeImmutable;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use function is_array;
use function is_string;

/**
 * Works out which site and period a report page shows, and puts them on the request as a ReportContext. They come from
 * the "site", "period", "from" and "to" query parameters, which the report header's form sends, and are remembered in
 * the session, so moving between reports keeps them. Add it after AdminLayoutMiddleware.
 */
final readonly class ReportContextMiddleware implements MiddlewareInterface
{
    private const SESSION_KEY = 'analytics-report';

    public function __construct(
        private SiteModel $sites,
        private TrackingConfig $config,
        private TemplateRendererInterface $template,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(User::class);
        if (! $user instanceof User) {
            throw new RuntimeException('AdminLayoutMiddleware must run before ' . self::class);
        }

        $sites = $this->sites->findForUser($user);
        if ($sites === []) {
            return new HtmlResponse($this->template->render('admin::no-sites', [
                'isAdmin' => $user->isAdmin(),
            ]), 200, ['Cache-Control' => 'no-store']);
        }

        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        $stored  = $session instanceof SessionInterface ? $session->get(self::SESSION_KEY) : null;
        $stored  = is_array($stored) ? $stored : [];
        $query   = $request->getQueryParams();

        $choice = [
            'site'   => $this->string($query, 'site') ?? $this->string($stored, 'site'),
            'period' => $this->string($query, 'period') ?? $this->string($stored, 'period') ?? Period::DEFAULT,
            'from'   => $this->string($query, 'from') ?? $this->string($stored, 'from'),
            'to'     => $this->string($query, 'to') ?? $this->string($stored, 'to'),
        ];

        $site = $sites[0];
        foreach ($sites as $candidate) {
            if ((string) $candidate->getId() === $choice['site']) {
                $site = $candidate;
                break;
            }
        }

        $period = Period::create(
            (string) $choice['period'],
            $choice['from'],
            $choice['to'],
            new DateTimeImmutable('today', $this->config->timezone)
        );

        if ($session instanceof SessionInterface) {
            $session->set(self::SESSION_KEY, [
                'site'   => (string) $site->getId(),
                'period' => $period->key,
                'from'   => $period->from->format('Y-m-d'),
                'to'     => $period->to->format('Y-m-d'),
            ]);
        }

        $context = new ReportContext($user, $site, $sites, $period);
        $this->template->addDefaultParam(TemplateRendererInterface::TEMPLATE_ALL, 'context', $context);

        return $handler->handle($request->withAttribute(ReportContext::class, $context));
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function string(array $values, string $name): ?string
    {
        $value = $values[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
