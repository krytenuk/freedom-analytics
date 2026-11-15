<?php

declare(strict_types=1);

namespace Admin\Model;

use Admin\Report\Period;
use App\Entity\Event;
use App\Entity\Page;
use App\Entity\Pageview;
use App\Entity\Site;
use App\Entity\Visit;
use App\Tracking\TrackingConfig;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Locale;

use function array_fill_keys;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_slice;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_numeric;
use function range;
use function sprintf;
use function str_pad;
use function substr;
use function ucfirst;
use function usort;

use const PHP_INT_MAX;
use const STR_PAD_LEFT;

/**
 * The reporting queries. Every SQL fragment comes from the constants below, never from the request, so a report can
 * only group by a known dimension and sort by a known metric.
 *
 * Visits carry where the visitor came from and their browser, so most reports read only the visits table, as Open Web
 * Analytics' session fact table does. Page reports read page views, and action reports read events.
 */
class ReportModel
{
    public const SOURCE_VISITS    = 'visits';
    public const SOURCE_PAGEVIEWS = 'pageviews';
    public const SOURCE_EVENTS    = 'events';

    /**
     * The columns each kind of report shows, with how to show them
     *
     * @var array<string, array<string, array{label: string, format: string}>>
     */
    public const METRICS = [
        self::SOURCE_VISITS    => [
            'visits'          => ['label' => 'Visits', 'format' => 'number'],
            'visitors'        => ['label' => 'Visitors', 'format' => 'number'],
            'pageviews'       => ['label' => 'Page Views', 'format' => 'number'],
            'pages_per_visit' => ['label' => 'Pages / Visit', 'format' => 'decimal'],
            'avg_duration'    => ['label' => 'Avg. Visit Length', 'format' => 'duration'],
            'bounce_rate'     => ['label' => 'Bounce Rate', 'format' => 'percent'],
        ],
        self::SOURCE_PAGEVIEWS => [
            'pageviews'        => ['label' => 'Page Views', 'format' => 'number'],
            'unique_pageviews' => ['label' => 'Unique Page Views', 'format' => 'number'],
            'avg_time_on_page' => ['label' => 'Avg. Time on Page', 'format' => 'duration'],
        ],
        self::SOURCE_EVENTS    => [
            'events'      => ['label' => 'Events', 'format' => 'number'],
            'visits'      => ['label' => 'Visits', 'format' => 'number'],
            'total_value' => ['label' => 'Total Value', 'format' => 'decimal'],
        ],
    ];

    /** The SQL each metric is summed from. Rates are worked out from the sums, so buckets can be added up. */
    private const AGGREGATES = [
        self::SOURCE_VISITS    => [
            'visits'         => 'COUNT(*)',
            'visitors'       => 'COUNT(DISTINCT v.visitor_id)',
            'pageviews'      => 'SUM(v.num_pageviews)',
            'bounces'        => 'SUM(v.is_bounce)',
            'duration_total' => 'SUM(v.duration)',
        ],
        self::SOURCE_PAGEVIEWS => [
            'pageviews'        => 'COUNT(*)',
            'unique_pageviews' => 'COUNT(DISTINCT p.visit_id)',
            'time_total'       => 'SUM(p.time_on_page)',
            'timed_views'      => 'COUNT(p.time_on_page)',
        ],
        self::SOURCE_EVENTS    => [
            'events'      => 'COUNT(*)',
            'visits'      => 'COUNT(DISTINCT e.visit_id)',
            'total_value' => 'SUM(e.value)',
        ],
    ];

    private const TABLES = [
        self::SOURCE_VISITS    => ['visits', 'v', 'v.visit_date'],
        self::SOURCE_PAGEVIEWS => ['pageviews', 'p', 'p.visit_date'],
        self::SOURCE_EVENTS    => ['events', 'e', 'e.visit_date'],
    ];

    /**
     * - source:  the table the report reads
     * - expr:    what it groups by
     * - extra:   more columns to group by and show
     * - where:   which rows count
     * - page:    joins the pages table on this column, as pg
     * - buckets: groups the values into ranges, in a fixed order, so the report is not paged or sorted
     *
     * @var array<string, array{
     *     source: string,
     *     expr: string,
     *     extra?: array<string, string>,
     *     where?: string,
     *     page?: string,
     *     buckets?: string
     * }>
     */
    public const DIMENSIONS = [
        'country'               => ['source' => self::SOURCE_VISITS, 'expr' => 'v.country'],
        'language'              => ['source' => self::SOURCE_VISITS, 'expr' => 'v.language'],
        'browser'               => ['source' => self::SOURCE_VISITS, 'expr' => 'v.browser'],
        'browser_version'       => [
            'source' => self::SOURCE_VISITS,
            'expr'   => "CONCAT_WS(' ', v.browser, v.browser_version)",
        ],
        'os'                    => ['source' => self::SOURCE_VISITS, 'expr' => 'v.os'],
        'device_type'           => ['source' => self::SOURCE_VISITS, 'expr' => 'v.device_type'],
        'screen_resolution'     => ['source' => self::SOURCE_VISITS, 'expr' => 'v.screen_resolution'],
        'visit_number'          => [
            'source'  => self::SOURCE_VISITS,
            'expr'    => 'v.visit_number',
            'buckets' => 'loyalty',
        ],
        'days_since_last_visit' => [
            'source'  => self::SOURCE_VISITS,
            'expr'    => 'v.days_since_last_visit',
            'where'   => 'v.days_since_last_visit IS NOT NULL',
            'buckets' => 'recency',
        ],
        'visit_hour'            => ['source' => self::SOURCE_VISITS, 'expr' => 'v.visit_hour', 'buckets' => 'hours'],
        'medium'                => ['source' => self::SOURCE_VISITS, 'expr' => 'v.medium'],
        'source'                => [
            'source' => self::SOURCE_VISITS,
            'expr'   => 'v.source',
            'extra'  => ['medium' => 'v.medium'],
            'where'  => "v.medium <> 'direct'",
        ],
        'referrer_host'         => [
            'source' => self::SOURCE_VISITS,
            'expr'   => 'v.referrer_host',
            'where'  => "v.medium = 'referral'",
        ],
        'search_engine'         => [
            'source' => self::SOURCE_VISITS,
            'expr'   => 'v.source',
            'where'  => "v.medium = 'organic'",
        ],
        'search_terms'          => [
            'source' => self::SOURCE_VISITS,
            'expr'   => 'v.search_terms',
            'extra'  => ['source' => 'v.source'],
            'where'  => 'v.search_terms IS NOT NULL',
        ],
        'social_network'        => [
            'source' => self::SOURCE_VISITS,
            'expr'   => 'v.source',
            'where'  => "v.medium = 'social'",
        ],
        'campaign'              => [
            'source' => self::SOURCE_VISITS,
            'expr'   => 'v.campaign',
            'extra'  => ['source' => 'v.source'],
            'where'  => "v.medium = 'campaign'",
        ],
        'entry_page'            => ['source' => self::SOURCE_VISITS, 'expr' => 'pg.id', 'page' => 'v.entry_page_id'],
        'exit_page'             => ['source' => self::SOURCE_VISITS, 'expr' => 'pg.id', 'page' => 'v.exit_page_id'],
        'page'                  => ['source' => self::SOURCE_PAGEVIEWS, 'expr' => 'pg.id', 'page' => 'p.page_id'],
        'page_title'            => ['source' => self::SOURCE_PAGEVIEWS, 'expr' => 'pg.title', 'page' => 'p.page_id'],
        'event_category'        => ['source' => self::SOURCE_EVENTS, 'expr' => 'e.category'],
        'event_action'          => [
            'source' => self::SOURCE_EVENTS,
            'expr'   => 'e.action',
            'extra'  => ['category' => 'e.category'],
        ],
        'event_label'           => [
            'source' => self::SOURCE_EVENTS,
            'expr'   => 'e.label',
            'extra'  => ['category' => 'e.category', 'action' => 'e.action'],
            'where'  => 'e.label IS NOT NULL',
        ],
    ];

    private const DEVICE_LABELS = ['desktop' => 'Desktop', 'mobile' => 'Mobile', 'tablet' => 'Tablet'];

    private Connection $connection;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TrackingConfig $config,
    ) {
        $this->connection = $entityManager->getConnection();
    }

    /**
     * The headline numbers for a period
     *
     * @return array{
     *     visits: int,
     *     visitors: int,
     *     pageviews: int,
     *     pages_per_visit: float,
     *     avg_duration: float,
     *     bounce_rate: float,
     *     new_visits: int,
     *     new_visit_rate: float,
     *     events: int
     * }
     */
    public function kpis(Site $site, Period $period): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS visits, COUNT(DISTINCT v.visitor_id) AS visitors,'
            . ' COALESCE(SUM(v.num_pageviews), 0) AS pageviews, COALESCE(SUM(v.is_bounce), 0) AS bounces,'
            . ' COALESCE(SUM(v.duration), 0) AS duration_total, COALESCE(SUM(v.is_new_visitor), 0) AS new_visits,'
            . ' COALESCE(SUM(v.num_events), 0) AS events'
            . ' FROM visits v WHERE v.site_id = :site AND v.visit_date BETWEEN :from AND :to',
            $this->params($site, $period)
        ) ?: [];

        $visits = (int) ($row['visits'] ?? 0);

        return [
            'visits'          => $visits,
            'visitors'        => (int) ($row['visitors'] ?? 0),
            'pageviews'       => (int) ($row['pageviews'] ?? 0),
            'pages_per_visit' => $this->ratio($row['pageviews'] ?? 0, $visits),
            'avg_duration'    => $this->ratio($row['duration_total'] ?? 0, $visits),
            'bounce_rate'     => $this->ratio($row['bounces'] ?? 0, $visits),
            'new_visits'      => (int) ($row['new_visits'] ?? 0),
            'new_visit_rate'  => $this->ratio($row['new_visits'] ?? 0, $visits),
            'events'          => (int) ($row['events'] ?? 0),
        ];
    }

    /**
     * Visits, visitors, page views and bounce rate for each day of the period, with days that had none
     *
     * @return list<array{
     *     date: string,
     *     visits: int,
     *     visitors: int,
     *     pageviews: int,
     *     new_visits: int,
     *     bounce_rate: float
     * }>
     */
    public function trend(Site $site, Period $period): array
    {
        $rows = $this->connection->fetchAllAssociativeIndexed(
            'SELECT v.visit_date AS day, COUNT(*) AS visits, COUNT(DISTINCT v.visitor_id) AS visitors,'
            . ' SUM(v.num_pageviews) AS pageviews, SUM(v.is_new_visitor) AS new_visits, SUM(v.is_bounce) AS bounces'
            . ' FROM visits v WHERE v.site_id = :site AND v.visit_date BETWEEN :from AND :to'
            . ' GROUP BY v.visit_date',
            $this->params($site, $period)
        );

        $trend = [];
        foreach (new DatePeriod($period->from, new DateInterval('P1D'), $period->to->modify('+1 day')) as $day) {
            $key     = $day->format('Y-m-d');
            $row     = $rows[$key] ?? [];
            $visits  = (int) ($row['visits'] ?? 0);
            $trend[] = [
                'date'        => $key,
                'visits'      => $visits,
                'visitors'    => (int) ($row['visitors'] ?? 0),
                'pageviews'   => (int) ($row['pageviews'] ?? 0),
                'new_visits'  => (int) ($row['new_visits'] ?? 0),
                'bounce_rate' => $this->ratio($row['bounces'] ?? 0, $visits),
            ];
        }

        return $trend;
    }

    /**
     * Visitors with a page view or event in the last few minutes
     */
    public function activeVisitors(Site $site, int $minutes = 5): int
    {
        $since = (new DateTimeImmutable('now', $this->config->timezone))->modify(sprintf('-%d minutes', $minutes));

        return (int) $this->connection->fetchOne(
            'SELECT COUNT(DISTINCT visitor_id) FROM visits WHERE site_id = :site AND last_activity_at >= :since',
            ['site' => $site->getId(), 'since' => $since],
            ['since' => Types::DATETIME_IMMUTABLE]
        );
    }

    /**
     * What is happening on the site now, for the live page: the visits active in the last few minutes, the pages
     * their visitors are on, page views each minute and the latest page views and events.
     *
     * Everything starts from visits with recent activity (idx_visits_site_activity), since every recent page view or
     * event belongs to one, and page views and events are then found by their visit.
     *
     * @return array{
     *     now: DateTimeImmutable,
     *     active: int,
     *     visits: list<Visit>,
     *     visit_count: int,
     *     pageviews: int,
     *     events: int,
     *     minutes: list<array{minute: string, pageviews: int}>,
     *     pages: list<array{id: int, path: string, title: string|null, visitors: int}>,
     *     activity: list<array{type: string, at: DateTimeImmutable, pageview?: Pageview, event?: Event}>
     * }
     */
    public function live(Site $site, int $windowMinutes = 30, int $activeMinutes = 5, int $limit = 50): array
    {
        $now    = new DateTimeImmutable('now', $this->config->timezone);
        $from   = self::minuteStart($now)->modify(sprintf('-%d minutes', $windowMinutes - 1));
        $active = $now->modify(sprintf('-%d minutes', $activeMinutes));
        $params = ['site' => $site->getId(), 'since' => $from];
        $types  = ['since' => Types::DATETIME_IMMUTABLE];

        $visitCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM visits v WHERE v.site_id = :site AND v.last_activity_at >= :since',
            $params,
            $types
        );

        /** @var list<string> $viewTimes */
        $viewTimes = $this->connection->fetchFirstColumn(
            'SELECT p.viewed_at FROM visits v JOIN pageviews p ON p.visit_id = v.id'
            . ' WHERE v.site_id = :site AND v.last_activity_at >= :since AND p.viewed_at >= :since',
            $params,
            $types
        );

        $events = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM visits v JOIN events e ON e.visit_id = v.id'
            . ' WHERE v.site_id = :site AND v.last_activity_at >= :since AND e.occurred_at >= :since',
            $params,
            $types
        );

        $pages = $this->connection->fetchAllAssociative(
            'SELECT pg.id, pg.path, pg.title, COUNT(DISTINCT v.visitor_id) AS visitors'
            . ' FROM visits v JOIN pages pg ON pg.id = v.exit_page_id'
            . ' WHERE v.site_id = :site AND v.last_activity_at >= :active'
            . ' GROUP BY pg.id, pg.path, pg.title ORDER BY visitors DESC, pg.path LIMIT 10',
            ['site' => $site->getId(), 'active' => $active],
            ['active' => Types::DATETIME_IMMUTABLE]
        );

        /** @var list<Visit> $visits */
        $visits = $this->entityManager->createQueryBuilder()
            ->select('v', 'exit', 'visitor')
            ->from(Visit::class, 'v')
            ->leftJoin('v.exitPage', 'exit')
            ->join('v.visitor', 'visitor')
            ->where('v.site = :site')
            ->andWhere('v.lastActivityAt >= :since')
            ->setParameter('site', $site)
            ->setParameter('since', $from, Types::DATETIME_IMMUTABLE)
            ->orderBy('v.lastActivityAt', 'DESC')
            ->addOrderBy('v.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'now'         => $now,
            'active'      => $this->activeVisitors($site, $activeMinutes),
            'visits'      => $visits,
            'visit_count' => $visitCount,
            'pageviews'   => count($viewTimes),
            'events'      => $events,
            'minutes'     => self::perMinute($viewTimes, $from, $windowMinutes),
            'pages'       => array_map(static fn (array $row): array => [
                'id'       => (int) $row['id'],
                'path'     => (string) $row['path'],
                'title'    => $row['title'] === null ? null : (string) $row['title'],
                'visitors' => (int) $row['visitors'],
            ], $pages),
            'activity'    => $this->recentActivity($site, $from, 20),
        ];
    }

    /**
     * Counts of the times in each minute from $from, with minutes that had none
     *
     * @param list<string> $times Database datetimes, "Y-m-d H:i:s"
     * @return list<array{minute: string, pageviews: int}> Minute as "H:i"
     */
    public static function perMinute(array $times, DateTimeImmutable $from, int $minutes): array
    {
        $counts = [];
        foreach ($times as $time) {
            $key          = substr($time, 0, 16);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $buckets = [];
        $minute  = self::minuteStart($from);
        for ($i = 0; $i < $minutes; $i++) {
            $buckets[] = [
                'minute'    => $minute->format('H:i'),
                'pageviews' => $counts[$minute->format('Y-m-d H:i')] ?? 0,
            ];
            $minute    = $minute->modify('+1 minute');
        }

        return $buckets;
    }

    /**
     * The latest page views and events since $since, newest first
     *
     * @return list<array{type: string, at: DateTimeImmutable, pageview?: Pageview, event?: Event}>
     */
    private function recentActivity(Site $site, DateTimeImmutable $since, int $limit): array
    {
        /** @var list<Pageview> $pageviews */
        $pageviews = $this->entityManager->createQueryBuilder()
            ->select('p', 'pg', 'v')
            ->from(Pageview::class, 'p')
            ->join('p.visit', 'v')
            ->join('p.page', 'pg')
            ->where('v.site = :site')
            ->andWhere('v.lastActivityAt >= :since')
            ->andWhere('p.viewedAt >= :since')
            ->setParameter('site', $site)
            ->setParameter('since', $since, Types::DATETIME_IMMUTABLE)
            ->orderBy('p.viewedAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        /** @var list<Event> $events */
        $events = $this->entityManager->createQueryBuilder()
            ->select('e', 'v')
            ->from(Event::class, 'e')
            ->join('e.visit', 'v')
            ->where('v.site = :site')
            ->andWhere('v.lastActivityAt >= :since')
            ->andWhere('e.occurredAt >= :since')
            ->setParameter('site', $site)
            ->setParameter('since', $since, Types::DATETIME_IMMUTABLE)
            ->orderBy('e.occurredAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $activity = [];
        foreach ($pageviews as $pageview) {
            $activity[] = ['type' => 'pageview', 'at' => $pageview->getViewedAt(), 'pageview' => $pageview];
        }
        foreach ($events as $event) {
            $activity[] = ['type' => 'event', 'at' => $event->getOccurredAt(), 'event' => $event];
        }

        // Newest first, and events after the page view they happened on
        usort(
            $activity,
            static fn (array $a, array $b): int
                => [$b['at'], $b['type'] === 'event'] <=> [$a['at'], $a['type'] === 'event']
        );

        return array_slice($activity, 0, $limit);
    }

    private static function minuteStart(DateTimeImmutable $time): DateTimeImmutable
    {
        return $time->setTime((int) $time->format('H'), (int) $time->format('i'));
    }

    /**
     * One page of a dimension report
     *
     * @param string|null $sort A metric of the dimension's source, or null for the main one
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     total: int,
     *     totals: array<string, int|float>,
     *     metrics: array<string, array{label: string, format: string}>,
     *     primary: string,
     *     sort: string,
     *     descending: bool,
     *     bucketed: bool
     * }
     */
    public function dimension(
        Site $site,
        Period $period,
        string $dimension,
        int $limit = 25,
        int $offset = 0,
        ?string $sort = null,
        bool $descending = true,
    ): array {
        if (! isset(self::DIMENSIONS[$dimension])) {
            throw new InvalidArgumentException('Unknown dimension ' . $dimension);
        }

        $definition = self::DIMENSIONS[$dimension];
        $source     = $definition['source'];
        $metrics    = self::METRICS[$source];
        $bucketed   = isset($definition['buckets']);
        if ($bucketed) {
            // Visitors cannot be added up across values, as one visitor can be in several
            unset($metrics['visitors']);
        }

        $primary = (string) array_keys($metrics)[0];
        $sort    = $sort !== null && array_key_exists($sort, $metrics) ? $sort : $primary;

        [$from, $where] = $this->fromWhere($definition);
        $groupBy        = array_merge([$definition['expr']], array_values($definition['extra'] ?? []));
        if ($definition['expr'] === 'pg.id') {
            $groupBy = array_merge($groupBy, ['pg.path', 'pg.title']);
        }

        $select = [$definition['expr'] . ' AS dim'];
        foreach ($definition['extra'] ?? [] as $alias => $expr) {
            $select[] = $expr . ' AS ' . $alias;
        }
        if ($definition['expr'] === 'pg.id') {
            $select[] = 'pg.path AS path';
            $select[] = 'pg.title AS title';
        }
        foreach (self::AGGREGATES[$source] as $alias => $aggregate) {
            $select[] = $aggregate . ' AS ' . $alias;
        }

        $params = $this->params($site, $period);
        $sql    = 'SELECT ' . implode(', ', $select) . ' FROM ' . $from . ' WHERE ' . $where
            . ' GROUP BY ' . implode(', ', $groupBy);

        $totals = $this->totals($source, $from, $where, $params);

        if ($bucketed) {
            $rows = $this->buckets(
                (string) $definition['buckets'],
                $source,
                $this->connection->fetchAllAssociative($sql, $params)
            );

            return [
                'rows'       => $this->withShares($rows, $primary, $totals),
                'total'      => count($rows),
                'totals'     => $totals,
                'metrics'    => $metrics,
                'primary'    => $primary,
                'sort'       => $primary,
                'descending' => true,
                'bucketed'   => true,
            ];
        }

        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM (' . $sql . ') grouped', $params);

        $orderBy = $this->orderBy($source, $sort) . ($descending ? ' DESC' : ' ASC') . ', dim ASC';
        $rows    = $this->connection->fetchAllAssociative(
            $sql . ' ORDER BY ' . $orderBy . sprintf(' LIMIT %d OFFSET %d', $limit, $offset),
            $params
        );

        $rows = array_map(fn (array $row): array => $this->finishRow($dimension, $source, $row), $rows);

        return [
            'rows'       => $this->withShares($rows, $primary, $totals),
            'total'      => $total,
            'totals'     => $totals,
            'metrics'    => $metrics,
            'primary'    => $primary,
            'sort'       => $sort,
            'descending' => $descending,
            'bucketed'   => false,
        ];
    }

    /**
     * The most recent visits of the period, newest first
     *
     * @return array{visits: list<Visit>, total: int}
     */
    public function latestVisits(Site $site, Period $period, int $limit, int $offset): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->from(Visit::class, 'v')
            ->where('v.site = :site')
            ->andWhere('v.visitDate BETWEEN :from AND :to')
            ->setParameter('site', $site)
            ->setParameter('from', $period->from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $period->to, Types::DATE_IMMUTABLE);

        $total = (int) (clone $qb)->select('COUNT(v.id)')->getQuery()->getSingleScalarResult();

        /** @var list<Visit> $visits */
        $visits = $qb
            ->select('v', 'entry', 'exit', 'visitor')
            ->leftJoin('v.entryPage', 'entry')
            ->leftJoin('v.exitPage', 'exit')
            ->join('v.visitor', 'visitor')
            ->orderBy('v.startedAt', 'DESC')
            ->addOrderBy('v.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['visits' => $visits, 'total' => $total];
    }

    public function findVisit(Site $site, int $id): ?Visit
    {
        $visit = $this->entityManager->find(Visit::class, $id);

        return $visit instanceof Visit && $visit->getSite() === $site ? $visit : null;
    }

    /**
     * A visit's page views and events, in the order they happened
     *
     * @return list<array{type: string, at: DateTimeImmutable, pageview?: Pageview, event?: Event}>
     */
    public function visitTimeline(Visit $visit): array
    {
        /** @var list<Pageview> $pageviews */
        $pageviews = $this->entityManager->createQueryBuilder()
            ->select('p', 'pg')
            ->from(Pageview::class, 'p')
            ->join('p.page', 'pg')
            ->where('p.visit = :visit')
            ->setParameter('visit', $visit)
            ->getQuery()
            ->getResult();

        /** @var list<Event> $events */
        $events = $this->entityManager->getRepository(Event::class)->findBy(['visit' => $visit]);

        $timeline = [];
        foreach ($pageviews as $pageview) {
            $timeline[] = ['type' => 'pageview', 'at' => $pageview->getViewedAt(), 'pageview' => $pageview];
        }
        foreach ($events as $event) {
            $timeline[] = ['type' => 'event', 'at' => $event->getOccurredAt(), 'event' => $event];
        }

        usort(
            $timeline,
            // Page views before events at the same moment
            static fn (array $a, array $b): int
                => [$a['at'], $a['type'] === 'event'] <=> [$b['at'], $b['type'] === 'event']
        );

        return $timeline;
    }

    /**
     * The visitor's other visits, newest first
     *
     * @return list<Visit>
     */
    public function visitorHistory(Visit $visit, int $limit = 10): array
    {
        /** @var list<Visit> $visits */
        $visits = $this->entityManager->createQueryBuilder()
            ->select('v')
            ->from(Visit::class, 'v')
            ->where('v.visitor = :visitor')
            ->andWhere('v <> :visit')
            ->setParameter('visitor', $visit->getVisitor())
            ->setParameter('visit', $visit)
            ->orderBy('v.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $visits;
    }

    public function findPage(Site $site, int $id): ?Page
    {
        $page = $this->entityManager->find(Page::class, $id);

        return $page instanceof Page && $page->getSite() === $site ? $page : null;
    }

    /**
     * A page's numbers for the period
     *
     * @return array{
     *     pageviews: int,
     *     unique_pageviews: int,
     *     avg_time_on_page: float,
     *     entrances: int,
     *     exits: int,
     *     bounce_rate: float,
     *     exit_rate: float,
     *     events: int
     * }
     */
    public function pageSummary(Site $site, Period $period, Page $page): array
    {
        $params = $this->params($site, $period) + ['page' => $page->getId()];

        $views = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS pageviews, COUNT(DISTINCT p.visit_id) AS unique_pageviews,'
            . ' SUM(p.time_on_page) AS time_total, COUNT(p.time_on_page) AS timed_views'
            . ' FROM pageviews p WHERE p.site_id = :site AND p.visit_date BETWEEN :from AND :to'
            . ' AND p.page_id = :page',
            $params
        ) ?: [];

        $entries = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS entrances, COALESCE(SUM(v.is_bounce), 0) AS bounces FROM visits v'
            . ' WHERE v.site_id = :site AND v.visit_date BETWEEN :from AND :to AND v.entry_page_id = :page',
            $params
        ) ?: [];

        $exits = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM visits v WHERE v.site_id = :site AND v.visit_date BETWEEN :from AND :to'
            . ' AND v.exit_page_id = :page',
            $params
        );

        $events = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM events e WHERE e.site_id = :site AND e.visit_date BETWEEN :from AND :to'
            . ' AND e.page_id = :page',
            $params
        );

        $pageviews = (int) ($views['pageviews'] ?? 0);

        return [
            'pageviews'        => $pageviews,
            'unique_pageviews' => (int) ($views['unique_pageviews'] ?? 0),
            'avg_time_on_page' => $this->ratio($views['time_total'] ?? 0, $views['timed_views'] ?? 0),
            'entrances'        => (int) ($entries['entrances'] ?? 0),
            'exits'            => $exits,
            'bounce_rate'      => $this->ratio($entries['bounces'] ?? 0, $entries['entrances'] ?? 0),
            'exit_rate'        => $this->ratio($exits, $pageviews),
            'events'           => $events,
        ];
    }

    /**
     * A page's views for each day of the period
     *
     * @return list<array{date: string, pageviews: int, unique_pageviews: int}>
     */
    public function pageTrend(Site $site, Period $period, Page $page): array
    {
        $rows = $this->connection->fetchAllAssociativeIndexed(
            'SELECT p.visit_date AS day, COUNT(*) AS pageviews, COUNT(DISTINCT p.visit_id) AS unique_pageviews'
            . ' FROM pageviews p WHERE p.site_id = :site AND p.visit_date BETWEEN :from AND :to'
            . ' AND p.page_id = :page GROUP BY p.visit_date',
            $this->params($site, $period) + ['page' => $page->getId()]
        );

        $trend = [];
        foreach (new DatePeriod($period->from, new DateInterval('P1D'), $period->to->modify('+1 day')) as $day) {
            $key     = $day->format('Y-m-d');
            $trend[] = [
                'date'             => $key,
                'pageviews'        => (int) ($rows[$key]['pageviews'] ?? 0),
                'unique_pageviews' => (int) ($rows[$key]['unique_pageviews'] ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * Where visitors went to this page from, by the page before it in the same visit
     *
     * @return list<array{page_id: int, path: string, title: string|null, count: int}>
     */
    public function previousPages(Site $site, Period $period, Page $page, int $limit = 10): array
    {
        return $this->neighbourPages($site, $period, $page, $limit, '<', 'MAX');
    }

    /**
     * Where visitors went next from this page
     *
     * @return list<array{page_id: int, path: string, title: string|null, count: int}>
     */
    public function nextPages(Site $site, Period $period, Page $page, int $limit = 10): array
    {
        return $this->neighbourPages($site, $period, $page, $limit, '>', 'MIN');
    }

    /**
     * @return list<array{page_id: int, path: string, title: string|null, count: int}>
     */
    private function neighbourPages(
        Site $site,
        Period $period,
        Page $page,
        int $limit,
        string $comparison,
        string $pick,
    ): array {
        // For each view of the page, the view just before or after it in the same visit
        $rows = $this->connection->fetchAllAssociative(
            'SELECT pg.id AS page_id, pg.path, pg.title, COUNT(*) AS count FROM pageviews p'
            . ' JOIN pageviews n ON n.id = (SELECT n2.id FROM pageviews n2 WHERE n2.visit_id = p.visit_id'
            . ' AND (n2.viewed_at ' . $comparison . ' p.viewed_at OR (n2.viewed_at = p.viewed_at AND n2.id '
            . $comparison . ' p.id))'
            . ' ORDER BY n2.viewed_at ' . ($pick === 'MAX' ? 'DESC' : 'ASC') . ', n2.id '
            . ($pick === 'MAX' ? 'DESC' : 'ASC') . ' LIMIT 1)'
            . ' JOIN pages pg ON pg.id = n.page_id'
            . ' WHERE p.site_id = :site AND p.visit_date BETWEEN :from AND :to AND p.page_id = :page'
            . ' GROUP BY pg.id, pg.path, pg.title ORDER BY count DESC, pg.path ASC'
            . sprintf(' LIMIT %d', $limit),
            $this->params($site, $period) + ['page' => $page->getId()]
        );

        return array_map(static fn (array $row): array => [
            'page_id' => (int) $row['page_id'],
            'path'    => (string) $row['path'],
            'title'   => $row['title'] === null ? null : (string) $row['title'],
            'count'   => (int) $row['count'],
        ], $rows);
    }

    /**
     * @param array{source: string, expr: string, where?: string, page?: string} $definition
     * @return array{0: string, 1: string}
     */
    private function fromWhere(array $definition): array
    {
        [$table, $alias, $date] = self::TABLES[$definition['source']];

        $from = $table . ' ' . $alias;
        if (isset($definition['page'])) {
            $from .= ' JOIN pages pg ON pg.id = ' . $definition['page'];
        }

        $where = $alias . '.site_id = :site AND ' . $date . ' BETWEEN :from AND :to';
        if (isset($definition['where'])) {
            $where .= ' AND ' . $definition['where'];
        }

        return [$from, $where];
    }

    /**
     * The whole period's sums, for each row's share
     *
     * @param array<string, mixed> $params
     * @return array<string, int|float>
     */
    private function totals(string $source, string $from, string $where, array $params): array
    {
        $select = [];
        foreach (self::AGGREGATES[$source] as $alias => $aggregate) {
            $select[] = $aggregate . ' AS ' . $alias;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT ' . implode(', ', $select) . ' FROM ' . $from . ' WHERE ' . $where,
            $params
        ) ?: [];

        return $this->metricsFromSums($source, $row);
    }

    /**
     * Turns a row of sums into the report's metrics
     *
     * @param array<string, mixed> $row
     * @return array<string, int|float>
     */
    private function metricsFromSums(string $source, array $row): array
    {
        return match ($source) {
            self::SOURCE_VISITS    => [
                'visits'          => (int) ($row['visits'] ?? 0),
                'visitors'        => (int) ($row['visitors'] ?? 0),
                'pageviews'       => (int) ($row['pageviews'] ?? 0),
                'pages_per_visit' => $this->ratio($row['pageviews'] ?? 0, $row['visits'] ?? 0),
                'avg_duration'    => $this->ratio($row['duration_total'] ?? 0, $row['visits'] ?? 0),
                'bounce_rate'     => $this->ratio($row['bounces'] ?? 0, $row['visits'] ?? 0),
            ],
            self::SOURCE_PAGEVIEWS => [
                'pageviews'        => (int) ($row['pageviews'] ?? 0),
                'unique_pageviews' => (int) ($row['unique_pageviews'] ?? 0),
                'avg_time_on_page' => $this->ratio($row['time_total'] ?? 0, $row['timed_views'] ?? 0),
            ],
            default                => [
                'events'      => (int) ($row['events'] ?? 0),
                'visits'      => (int) ($row['visits'] ?? 0),
                'total_value' => (float) ($row['total_value'] ?? 0),
            ],
        };
    }

    /**
     * The SQL to sort by a metric, from its sums
     */
    private function orderBy(string $source, string $metric): string
    {
        return match ($metric) {
            'pages_per_visit'  => 'SUM(v.num_pageviews) / COUNT(*)',
            'avg_duration'     => 'AVG(v.duration)',
            'bounce_rate'      => 'AVG(v.is_bounce)',
            'avg_time_on_page' => 'AVG(p.time_on_page)',
            default            => self::AGGREGATES[$source][$metric],
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function finishRow(string $dimension, string $source, array $row): array
    {
        $key = $row['dim'] ?? null;

        $finished = [
            'key'   => $key === null ? null : (string) $key,
            'label' => $this->label($dimension, $key === null ? null : (string) $key, $row),
        ] + $this->metricsFromSums($source, $row);

        foreach (['path', 'title', 'medium', 'source', 'category', 'action'] as $column) {
            if (array_key_exists($column, $row)) {
                $finished[$column] = $row[$column];
            }
        }
        if (isset($finished['medium'])) {
            $finished['medium'] = Visit::MEDIUMS[$finished['medium']] ?? $finished['medium'];
        }

        return $finished;
    }

    /**
     * How a value is shown
     *
     * @param array<string, mixed> $row
     */
    private function label(string $dimension, ?string $value, array $row): string
    {
        if (isset($row['path'])) {
            return (string) $row['path'];
        }

        if ($value === null || $value === '') {
            return match ($dimension) {
                'campaign'   => '(not set)',
                'page_title' => '(no title)',
                default      => '(unknown)',
            };
        }

        return match ($dimension) {
            'country'     => $this->countryName($value),
            'language'    => Locale::getDisplayName($value, 'en') . ' (' . $value . ')',
            'medium'      => Visit::MEDIUMS[$value] ?? $value,
            'device_type' => self::DEVICE_LABELS[$value] ?? ucfirst($value),
            default       => $value,
        };
    }

    public function countryName(string $code): string
    {
        $name = Locale::getDisplayRegion('-' . $code, 'en');

        return $name === '' || $name === $code ? $code : $name;
    }

    /**
     * Groups rows of a numeric dimension into ranges, in order, with ranges that had none
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function buckets(string $kind, string $source, array $rows): array
    {
        $ranges = match ($kind) {
            'loyalty' => [
                [1, 1, 'First visit'],
                [2, 2, '2nd visit'],
                [3, 3, '3rd visit'],
                [4, 4, '4th visit'],
                [5, 5, '5th visit'],
                [6, 10, '6–10'],
                [11, 25, '11–25'],
                [26, 50, '26–50'],
                [51, PHP_INT_MAX, '51 or more'],
            ],
            'recency' => [
                [0, 0, 'Same day'],
                [1, 1, '1 day'],
                [2, 7, '2–7 days'],
                [8, 14, '8–14 days'],
                [15, 30, '15–30 days'],
                [31, 60, '31–60 days'],
                [61, 120, '61–120 days'],
                [121, 364, '121–364 days'],
                [365, PHP_INT_MAX, 'A year or more'],
            ],
            default   => array_map(
                static fn (int $hour): array => [$hour, $hour, str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00'],
                range(0, 23)
            ),
        };

        $sums = [];
        foreach ($ranges as $index => $range) {
            $sums[$index] = array_fill_keys(array_keys(self::AGGREGATES[$source]), 0);
        }

        foreach ($rows as $row) {
            if (! is_numeric($row['dim'] ?? null)) {
                continue;
            }
            $value = (int) $row['dim'];
            foreach ($ranges as $index => [$min, $max]) {
                if ($value >= $min && $value <= $max) {
                    foreach (array_keys($sums[$index]) as $alias) {
                        $sums[$index][$alias] += (float) ($row[$alias] ?? 0);
                    }
                    break;
                }
            }
        }

        $buckets = [];
        foreach ($ranges as $index => $range) {
            $buckets[] = ['key' => (string) $range[0], 'label' => $range[2]]
                + $this->metricsFromSums($source, $sums[$index]);
        }

        return $buckets;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, int|float> $totals
     * @return list<array<string, mixed>>
     */
    private function withShares(array $rows, string $primary, array $totals): array
    {
        $total = (float) ($totals[$primary] ?? 0);

        return array_map(static function (array $row) use ($primary, $total): array {
            $row['share'] = $total > 0 ? (float) $row[$primary] / $total : 0.0;

            return $row;
        }, $rows);
    }

    /**
     * @return array{site: int|null, from: string, to: string}
     */
    private function params(Site $site, Period $period): array
    {
        return [
            'site' => $site->getId(),
            'from' => $period->from->format('Y-m-d'),
            'to'   => $period->to->format('Y-m-d'),
        ];
    }

    private function ratio(mixed $numerator, mixed $denominator): float
    {
        $denominator = (float) $denominator;

        return $denominator > 0 ? (float) $numerator / $denominator : 0.0;
    }

    /**
     * Whether a dimension's rows link to something
     */
    public static function isPageDimension(string $dimension): bool
    {
        return in_array($dimension, ['page', 'entry_page', 'exit_page'], true);
    }
}
