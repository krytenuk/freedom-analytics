<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A browser that has visited a site, known by the random id the tracker keeps in a first party cookie. Without the
 * cookie, the id is a hash that changes each day, so the visitor cannot be followed from day to day.
 */
#[ORM\Entity]
#[ORM\Table(name: 'visitors')]
#[ORM\UniqueConstraint(name: 'uniq_visitors_site_key', columns: ['site_id', 'visitor_key'])]
class Visitor
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(name: 'visitor_key', type: Types::STRING, length: 64)]
    private string $visitorKey;

    #[ORM\Column(name: 'first_seen_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $firstSeenAt;

    #[ORM\Column(name: 'last_seen_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $lastSeenAt;

    #[ORM\Column(name: 'num_visits', type: Types::INTEGER, options: ['unsigned' => true, 'default' => 0])]
    private int $numVisits = 0;

    public function __construct(Site $site, string $visitorKey, DateTimeImmutable $now)
    {
        $this->site        = $site;
        $this->visitorKey  = $visitorKey;
        $this->firstSeenAt = $now;
        $this->lastSeenAt  = $now;
    }

    public function getId(): ?int
    {
        return $this->id === null ? null : (int) $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getVisitorKey(): string
    {
        return $this->visitorKey;
    }

    public function getFirstSeenAt(): DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function getLastSeenAt(): DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(DateTimeImmutable $lastSeenAt): static
    {
        $this->lastSeenAt = $lastSeenAt;

        return $this;
    }

    public function getNumVisits(): int
    {
        return $this->numVisits;
    }

    public function incrementVisits(): static
    {
        $this->numVisits++;

        return $this;
    }
}
