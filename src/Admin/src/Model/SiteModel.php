<?php

declare(strict_types=1);

namespace Admin\Model;

use App\Entity\Site;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lists, finds, saves and deletes the sites being tracked
 */
class SiteModel
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(int $id): ?Site
    {
        return $this->entityManager->find(Site::class, $id);
    }

    /**
     * @return list<Site>
     */
    public function findAll(): array
    {
        /** @var list<Site> $result */
        $result = $this->entityManager->getRepository(Site::class)->findBy([], ['name' => 'ASC']);

        return $result;
    }

    /**
     * The sites a user can see: every site for an admin, or the ones they have been given
     *
     * @return list<Site>
     */
    public function findForUser(User $user): array
    {
        if ($user->isAdmin()) {
            return $this->findAll();
        }

        /** @var list<Site> $result */
        $result = $user->getSites()->getValues();

        return $result;
    }

    public function isDomainTaken(string $domain, ?Site $except = null): bool
    {
        $found = $this->entityManager->getRepository(Site::class)->findOneBy(['domain' => $domain]);

        return $found !== null && $found !== $except;
    }

    /**
     * @param array<string, mixed> $values The form's filtered values
     */
    public function save(Site $site, array $values): void
    {
        $site
            ->setName((string) $values['name'])
            ->setDomain((string) $values['domain'])
            ->setDescription((string) ($values['description'] ?? ''))
            ->setExcludedIps((string) ($values['excludedIps'] ?? ''))
            ->setCheckDomain((bool) ($values['checkDomain'] ?? false))
            ->setRespectDoNotTrack((bool) ($values['respectDoNotTrack'] ?? false))
            ->setActive((bool) ($values['active'] ?? false));

        if ($site->getId() === null) {
            $this->entityManager->persist($site);
        }

        $this->entityManager->flush();
    }

    /**
     * Gives the site a new key, so the old tracking code stops working
     */
    public function regenerateKey(Site $site): void
    {
        $site->setSiteKey(Site::generateKey());
        $this->entityManager->flush();
    }

    /**
     * Deletes the site with all its visits, page views and events, which the foreign keys remove with it
     */
    public function delete(Site $site): void
    {
        $this->entityManager->remove($site);
        $this->entityManager->flush();
    }

    /**
     * How much the site has collected, and when it last heard from it
     *
     * @return array{
     *     visits: int,
     *     pageviews: int,
     *     firstVisit: DateTimeImmutable|null,
     *     lastActivity: DateTimeImmutable|null
     * }
     */
    public function stats(Site $site): array
    {
        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT COUNT(*) AS visits, COALESCE(SUM(num_pageviews), 0) AS pageviews, MIN(started_at) AS first_visit,'
            . ' MAX(last_activity_at) AS last_activity FROM visits WHERE site_id = :site',
            ['site' => $site->getId()]
        ) ?: [];

        return [
            'visits'       => (int) ($row['visits'] ?? 0),
            'pageviews'    => (int) ($row['pageviews'] ?? 0),
            'firstVisit'   => isset($row['first_visit']) ? new DateTimeImmutable((string) $row['first_visit']) : null,
            'lastActivity' => isset($row['last_activity'])
                ? new DateTimeImmutable((string) $row['last_activity'])
                : null,
        ];
    }
}
