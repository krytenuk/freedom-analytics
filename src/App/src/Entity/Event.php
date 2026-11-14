<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Something a visitor did that the site reports with trackEvent(), such as downloading a file or sending a form. Open
 * Web Analytics calls these actions, with a group (here the category), a name (the action), a label and a value.
 */
#[ORM\Entity]
#[ORM\Table(name: 'events')]
#[ORM\Index(name: 'idx_events_site_date', columns: ['site_id', 'visit_date'])]
#[ORM\Index(name: 'idx_events_visit', columns: ['visit_id', 'occurred_at'])]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\ManyToOne(targetEntity: Visit::class)]
    #[ORM\JoinColumn(name: 'visit_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Visit $visit;

    #[ORM\ManyToOne(targetEntity: Visitor::class)]
    #[ORM\JoinColumn(name: 'visitor_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Visitor $visitor;

    /** The page it happened on */
    #[ORM\ManyToOne(targetEntity: Page::class)]
    #[ORM\JoinColumn(name: 'page_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Page $page;

    #[ORM\Column(name: 'occurred_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $occurredAt;

    #[ORM\Column(name: 'visit_date', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $visitDate;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $category;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $action;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $label;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, nullable: true)]
    private ?string $value;

    public function __construct(
        Visit $visit,
        ?Page $page,
        DateTimeImmutable $occurredAt,
        DateTimeImmutable $visitDate,
        string $category,
        string $action,
        ?string $label = null,
        ?float $value = null,
    ) {
        $this->site       = $visit->getSite();
        $this->visit      = $visit;
        $this->visitor    = $visit->getVisitor();
        $this->page       = $page;
        $this->occurredAt = $occurredAt;
        $this->visitDate  = $visitDate;
        $this->category   = $category;
        $this->action     = $action;
        $this->label      = $label;
        $this->value      = $value === null ? null : (string) $value;
    }

    public function getId(): ?int
    {
        return $this->id === null ? null : (int) $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getVisit(): Visit
    {
        return $this->visit;
    }

    public function getVisitor(): Visitor
    {
        return $this->visitor;
    }

    public function getPage(): ?Page
    {
        return $this->page;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getVisitDate(): DateTimeImmutable
    {
        return $this->visitDate;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getValue(): ?float
    {
        return $this->value === null ? null : (float) $this->value;
    }
}
