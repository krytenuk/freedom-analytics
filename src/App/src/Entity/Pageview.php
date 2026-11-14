<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function max;

/**
 * One view of a page, which Open Web Analytics calls a request
 */
#[ORM\Entity]
#[ORM\Table(name: 'pageviews')]
#[ORM\Index(name: 'idx_pageviews_site_date', columns: ['site_id', 'visit_date'])]
#[ORM\Index(name: 'idx_pageviews_visit', columns: ['visit_id', 'viewed_at'])]
class Pageview
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

    #[ORM\ManyToOne(targetEntity: Page::class)]
    #[ORM\JoinColumn(name: 'page_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Page $page;

    #[ORM\Column(name: 'viewed_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $viewedAt;

    #[ORM\Column(name: 'visit_date', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $visitDate;

    /** Seconds until the visitor's next page view in the visit. Unknown for the last page of a visit. */
    #[ORM\Column(name: 'time_on_page', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $timeOnPage = null;

    public function __construct(
        Visit $visit,
        Page $page,
        DateTimeImmutable $viewedAt,
        DateTimeImmutable $visitDate,
    ) {
        $this->site      = $visit->getSite();
        $this->visit     = $visit;
        $this->visitor   = $visit->getVisitor();
        $this->page      = $page;
        $this->viewedAt  = $viewedAt;
        $this->visitDate = $visitDate;
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

    public function getPage(): Page
    {
        return $this->page;
    }

    public function getViewedAt(): DateTimeImmutable
    {
        return $this->viewedAt;
    }

    public function getVisitDate(): DateTimeImmutable
    {
        return $this->visitDate;
    }

    public function getTimeOnPage(): ?int
    {
        return $this->timeOnPage;
    }

    /**
     * Records how long the page was looked at, from the time of the next page view
     */
    public function endAt(DateTimeImmutable $nextViewAt): static
    {
        $this->timeOnPage = max(0, $nextViewAt->getTimestamp() - $this->viewedAt->getTimestamp());

        return $this;
    }
}
