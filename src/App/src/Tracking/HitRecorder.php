<?php

declare(strict_types=1);

namespace App\Tracking;

use App\Entity\Event;
use App\Entity\Page;
use App\Entity\Pageview;
use App\Entity\Site;
use App\Entity\Visit;
use App\Entity\Visitor;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

use function assert;
use function explode;
use function mb_substr;
use function sha1;
use function sprintf;
use function str_starts_with;
use function substr;

/**
 * Saves a hit: finds or adds the visitor and the page, carries on the visitor's current visit or starts a new one, and
 * adds the page view or event to it. This is what Open Web Analytics' event processors do, without its queue.
 */
final class HitRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TrafficSourceClassifier $trafficSources,
        private readonly UrlNormalizer $urls,
        private readonly TrackingConfig $config,
    ) {
    }

    /**
     * @param string $visitorKey The tracker's visitor id, or one made from the request for visitors without a cookie
     * @param ClientInfo $client From the request's User-Agent
     * @param string|null $ipAddress Already anonymised if anonymize_ip is on
     */
    public function record(
        Site $site,
        Hit $hit,
        string $visitorKey,
        ClientInfo $client,
        ?string $userAgent,
        ?string $ipAddress,
        ?string $country,
        ?DateTimeImmutable $now = null,
    ): ?Visit {
        // The tracker can send a page view and an event at the same moment. Without the lock, both requests would
        // find no current visit and start one each, and one would overwrite the other's counts.
        // Hashed, as lock names are limited to 64 characters
        $lock = 'fa:' . sha1($site->getId() . ':' . $visitorKey);
        $this->acquireLock($lock);
        try {
            return $this->recordLocked(
                $site,
                $hit,
                $visitorKey,
                $client,
                $userAgent,
                $ipAddress,
                $country,
                $now,
            );
        } finally {
            $this->releaseLock($lock);
        }
    }

    private function recordLocked(
        Site $site,
        Hit $hit,
        string $visitorKey,
        ClientInfo $client,
        ?string $userAgent,
        ?string $ipAddress,
        ?string $country,
        ?DateTimeImmutable $now,
    ): ?Visit {
        $url = $this->urls->normalize($hit->url);
        if ($url === null) {
            return null;
        }

        $now ??= new DateTimeImmutable('now', $this->config->timezone);
        $now   = $now->setTimezone($this->config->timezone);
        $today = $now->setTime(0, 0);

        $visitor        = $this->findOrCreateVisitor($site, $visitorKey, $now);
        $previousSeenAt = $visitor->getNumVisits() > 0 ? $visitor->getLastSeenAt() : null;
        $visit          = $this->currentVisit($visitor, $now);

        if ($visit === null) {
            $visit = new Visit($site, $visitor, $now, $today);
            $visitor->incrementVisits();
            $visit->setVisitNumber($visitor->getNumVisits(), $previousSeenAt);

            $source = $this->trafficSources->classify($site, $hit->url, $hit->referrer);
            $visit
                ->setTrafficSource(
                    $source->medium,
                    $source->source,
                    $source->referrerUrl,
                    $source->referrerHost,
                    $source->campaign,
                    $source->campaignContent,
                    $source->searchTerms,
                )
                ->setClient(
                    $client->browser,
                    $client->browserVersion,
                    $client->os,
                    $client->osVersion,
                    $client->deviceType,
                    $userAgent === null ? null : mb_substr($userAgent, 0, 512),
                )
                ->setScreenResolution($hit->screenResolution)
                ->setLanguage($hit->language)
                ->setCountry($country)
                ->setIpAddress($ipAddress);

            $this->entityManager->persist($visit);
        }

        $visitor->setLastSeenAt($now);
        // Pages on a subdomain are shown with it, so shop.example.com/cart is not mistaken for /cart
        $path = $this->isMainHost($site, $url['host']) ? $url['path'] : $url['host'] . $url['path'];
        $page = $this->findOrCreatePage($site, $url['url'], mb_substr($path, 0, 768), $hit->title, $now);

        if ($hit->isEvent()) {
            $visit->addEvent();
            $this->entityManager->persist(new Event(
                $visit,
                $page,
                $now,
                $today,
                (string) $hit->eventCategory,
                (string) $hit->eventAction,
                $hit->eventLabel,
                $hit->eventValue,
            ));
        } else {
            $this->lastPageview($visit)?->endAt($now);
            $visit->addPageview($page);
            $this->entityManager->persist(new Pageview($visit, $page, $now, $today));
        }

        $visit->touch($now);
        $this->entityManager->flush();

        return $visit;
    }

    /**
     * A named lock that lasts until it is released or the connection closes, so it cannot be left behind. MariaDB and
     * MySQL only; other databases go without it.
     */
    private function acquireLock(string $name): void
    {
        if ($this->supportsLocks()) {
            // Waits up to 5 seconds, then records the hit anyway rather than lose it
            $this->entityManager->getConnection()->fetchOne('SELECT GET_LOCK(?, 5)', [$name]);
        }
    }

    private function releaseLock(string $name): void
    {
        if ($this->supportsLocks()) {
            $this->entityManager->getConnection()->fetchOne('SELECT RELEASE_LOCK(?)', [$name]);
        }
    }

    private function supportsLocks(): bool
    {
        return $this->entityManager->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform;
    }

    private function isMainHost(Site $site, string $host): bool
    {
        $strip = static fn (string $name): string => str_starts_with($name, 'www.') ? substr($name, 4) : $name;

        return $strip($host) === $strip(explode(':', $site->getDomain())[0]);
    }

    /**
     * The visitor's visit that is still going, if they have been active within the visit timeout
     */
    private function currentVisit(Visitor $visitor, DateTimeImmutable $now): ?Visit
    {
        if ($visitor->getId() === null) {
            return null;
        }

        $since = $now->sub(new DateInterval(sprintf('PT%dM', $this->config->visitTimeoutMinutes)));

        /** @var Visit|null $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('v')
            ->from(Visit::class, 'v')
            ->where('v.visitor = :visitor')
            ->andWhere('v.lastActivityAt >= :since')
            ->setParameter('visitor', $visitor)
            ->setParameter('since', $since, Types::DATETIME_IMMUTABLE)
            ->orderBy('v.lastActivityAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    private function lastPageview(Visit $visit): ?Pageview
    {
        if ($visit->getId() === null) {
            return null;
        }

        /** @var Pageview|null $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Pageview::class, 'p')
            ->where('p.visit = :visit')
            ->setParameter('visit', $visit)
            ->orderBy('p.viewedAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    /**
     * Two requests from a new visitor can arrive together, so the row is added with a plain insert, which leaves the
     * entity manager open if the other request added it first.
     */
    private function findOrCreateVisitor(Site $site, string $visitorKey, DateTimeImmutable $now): Visitor
    {
        $repository = $this->entityManager->getRepository(Visitor::class);
        $visitor    = $repository->findOneBy(['site' => $site, 'visitorKey' => $visitorKey]);
        if ($visitor instanceof Visitor) {
            return $visitor;
        }

        $this->insertIgnoringDuplicate('visitors', [
            'site_id'       => $site->getId(),
            'visitor_key'   => $visitorKey,
            'first_seen_at' => $now,
            'last_seen_at'  => $now,
            'num_visits'    => 0,
        ], [
            'first_seen_at' => Types::DATETIME_IMMUTABLE,
            'last_seen_at'  => Types::DATETIME_IMMUTABLE,
        ]);

        $visitor = $repository->findOneBy(['site' => $site, 'visitorKey' => $visitorKey]);
        assert($visitor instanceof Visitor);

        return $visitor;
    }

    private function findOrCreatePage(
        Site $site,
        string $url,
        string $path,
        ?string $title,
        DateTimeImmutable $now,
    ): Page {
        $repository = $this->entityManager->getRepository(Page::class);
        $criteria   = ['site' => $site, 'urlHash' => Page::hashUrl($url)];
        $page       = $repository->findOneBy($criteria);

        if (! $page instanceof Page) {
            $this->insertIgnoringDuplicate('pages', [
                'site_id'       => $site->getId(),
                'url_hash'      => Page::hashUrl($url),
                'url'           => $url,
                'path'          => $path,
                'title'         => $title,
                'first_seen_at' => $now,
            ], ['first_seen_at' => Types::DATETIME_IMMUTABLE]);

            $page = $repository->findOneBy($criteria);
            assert($page instanceof Page);
        }

        // Pages are renamed, so keep the latest title
        if ($title !== null && $page->getTitle() !== $title) {
            $page->setTitle($title);
        }

        return $page;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $types
     */
    private function insertIgnoringDuplicate(string $table, array $row, array $types): void
    {
        try {
            $this->entityManager->getConnection()->insert($table, $row, $types);
        } catch (UniqueConstraintViolationException) {
            // Another request added it first
        }
    }
}
