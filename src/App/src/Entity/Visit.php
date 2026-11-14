<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function max;

/**
 * One visit to a site, which Open Web Analytics calls a session: page views and events from one visitor with no gap
 * longer than the visit timeout. Where the visitor came from, and their browser, are recorded once here, so the
 * reports can group visits by them without reading every page view.
 */
#[ORM\Entity]
#[ORM\Table(name: 'visits')]
#[ORM\Index(name: 'idx_visits_site_date', columns: ['site_id', 'visit_date'])]
#[ORM\Index(name: 'idx_visits_visitor_activity', columns: ['visitor_id', 'last_activity_at'])]
#[ORM\Index(name: 'idx_visits_site_started', columns: ['site_id', 'started_at'])]
class Visit
{
    public const MEDIUM_DIRECT   = 'direct';
    public const MEDIUM_ORGANIC  = 'organic';
    public const MEDIUM_REFERRAL = 'referral';
    public const MEDIUM_SOCIAL   = 'social';
    public const MEDIUM_CAMPAIGN = 'campaign';

    public const MEDIUMS = [
        self::MEDIUM_DIRECT   => 'Direct',
        self::MEDIUM_ORGANIC  => 'Search Engine',
        self::MEDIUM_REFERRAL => 'Referring Site',
        self::MEDIUM_SOCIAL   => 'Social Network',
        self::MEDIUM_CAMPAIGN => 'Campaign',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\ManyToOne(targetEntity: Visitor::class)]
    #[ORM\JoinColumn(name: 'visitor_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Visitor $visitor;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $startedAt;

    #[ORM\Column(name: 'last_activity_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $lastActivityAt;

    /** The day the visit started, in the reporting timezone, so reports can group by day without date functions */
    #[ORM\Column(name: 'visit_date', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $visitDate;

    #[ORM\Column(name: 'visit_hour', type: Types::SMALLINT, options: ['unsigned' => true])]
    private int $visitHour;

    /** Seconds from the first to the last page view or event */
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true, 'default' => 0])]
    private int $duration = 0;

    #[ORM\Column(name: 'num_pageviews', type: Types::INTEGER, options: ['unsigned' => true, 'default' => 0])]
    private int $numPageviews = 0;

    #[ORM\Column(name: 'num_events', type: Types::INTEGER, options: ['unsigned' => true, 'default' => 0])]
    private int $numEvents = 0;

    /** A visit of one page view and nothing else */
    #[ORM\Column(name: 'is_bounce', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $bounce = true;

    #[ORM\Column(name: 'is_new_visitor', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $newVisitor = true;

    /** 1 for the visitor's first visit, 2 for their second, and so on */
    #[ORM\Column(name: 'visit_number', type: Types::INTEGER, options: ['unsigned' => true, 'default' => 1])]
    private int $visitNumber = 1;

    /** The visitor's previous visit, for the days since it */
    #[ORM\Column(name: 'days_since_last_visit', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $daysSinceLastVisit = null;

    #[ORM\ManyToOne(targetEntity: Page::class)]
    #[ORM\JoinColumn(name: 'entry_page_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Page $entryPage = null;

    #[ORM\ManyToOne(targetEntity: Page::class)]
    #[ORM\JoinColumn(name: 'exit_page_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Page $exitPage = null;

    #[ORM\Column(name: 'referrer_url', type: Types::TEXT, nullable: true)]
    private ?string $referrerUrl = null;

    #[ORM\Column(name: 'referrer_host', type: Types::STRING, length: 255, nullable: true)]
    private ?string $referrerHost = null;

    /** How the visitor arrived: one of the MEDIUM_ constants */
    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $medium = self::MEDIUM_DIRECT;

    /** Where from, such as "google", a referring site's host name, or a campaign's utm_source */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $campaign = null;

    #[ORM\Column(name: 'campaign_content', type: Types::STRING, length: 255, nullable: true)]
    private ?string $campaignContent = null;

    #[ORM\Column(name: 'search_terms', type: Types::STRING, length: 255, nullable: true)]
    private ?string $searchTerms = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $browser = null;

    #[ORM\Column(name: 'browser_version', type: Types::STRING, length: 20, nullable: true)]
    private ?string $browserVersion = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $os = null;

    #[ORM\Column(name: 'os_version', type: Types::STRING, length: 20, nullable: true)]
    private ?string $osVersion = null;

    /** desktop, mobile or tablet */
    #[ORM\Column(name: 'device_type', type: Types::STRING, length: 20, nullable: true)]
    private ?string $deviceType = null;

    #[ORM\Column(name: 'screen_resolution', type: Types::STRING, length: 20, nullable: true)]
    private ?string $screenResolution = null;

    /** The browser's language, such as en-gb */
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $language = null;

    /** Two letter country code */
    #[ORM\Column(type: Types::STRING, length: 2, nullable: true, options: ['fixed' => true])]
    private ?string $country = null;

    /** Anonymised, unless anonymize_ip is off */
    #[ORM\Column(name: 'ip_address', type: Types::STRING, length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(name: 'user_agent', type: Types::STRING, length: 512, nullable: true)]
    private ?string $userAgent = null;

    public function __construct(
        Site $site,
        Visitor $visitor,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $visitDate,
    ) {
        $this->site           = $site;
        $this->visitor        = $visitor;
        $this->startedAt      = $startedAt;
        $this->lastActivityAt = $startedAt;
        $this->visitDate      = $visitDate;
        $this->visitHour      = (int) $startedAt->format('G');
    }

    public function getId(): ?int
    {
        return $this->id === null ? null : (int) $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getVisitor(): Visitor
    {
        return $this->visitor;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getLastActivityAt(): DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    /**
     * Moves the end of the visit on, and works out its length again
     */
    public function touch(DateTimeImmutable $at): static
    {
        if ($at > $this->lastActivityAt) {
            $this->lastActivityAt = $at;
        }
        $this->duration = max(0, $this->lastActivityAt->getTimestamp() - $this->startedAt->getTimestamp());

        return $this;
    }

    public function getVisitDate(): DateTimeImmutable
    {
        return $this->visitDate;
    }

    public function getVisitHour(): int
    {
        return $this->visitHour;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function getNumPageviews(): int
    {
        return $this->numPageviews;
    }

    public function addPageview(Page $page): static
    {
        $this->numPageviews++;
        $this->entryPage ??= $page;
        $this->exitPage    = $page;
        $this->updateBounce();

        return $this;
    }

    public function getNumEvents(): int
    {
        return $this->numEvents;
    }

    public function addEvent(): static
    {
        $this->numEvents++;
        $this->updateBounce();

        return $this;
    }

    private function updateBounce(): void
    {
        $this->bounce = $this->numPageviews <= 1 && $this->numEvents === 0;
    }

    public function isBounce(): bool
    {
        return $this->bounce;
    }

    public function isNewVisitor(): bool
    {
        return $this->newVisitor;
    }

    public function getVisitNumber(): int
    {
        return $this->visitNumber;
    }

    /**
     * @param int $visitNumber 1 for the visitor's first visit
     * @param DateTimeImmutable|null $previousVisitAt When their previous visit ended, if they have had one
     */
    public function setVisitNumber(int $visitNumber, ?DateTimeImmutable $previousVisitAt = null): static
    {
        $this->visitNumber        = $visitNumber;
        $this->newVisitor         = $visitNumber <= 1;
        $this->daysSinceLastVisit = $previousVisitAt === null
            ? null
            : (int) $previousVisitAt->setTime(0, 0)->diff($this->startedAt->setTime(0, 0))->days;

        return $this;
    }

    public function getDaysSinceLastVisit(): ?int
    {
        return $this->daysSinceLastVisit;
    }

    public function getEntryPage(): ?Page
    {
        return $this->entryPage;
    }

    public function getExitPage(): ?Page
    {
        return $this->exitPage;
    }

    public function getReferrerUrl(): ?string
    {
        return $this->referrerUrl;
    }

    public function getReferrerHost(): ?string
    {
        return $this->referrerHost;
    }

    public function getMedium(): string
    {
        return $this->medium;
    }

    public function getMediumLabel(): string
    {
        return self::MEDIUMS[$this->medium] ?? $this->medium;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getCampaign(): ?string
    {
        return $this->campaign;
    }

    public function getCampaignContent(): ?string
    {
        return $this->campaignContent;
    }

    public function getSearchTerms(): ?string
    {
        return $this->searchTerms;
    }

    /**
     * Where the visitor came from
     */
    public function setTrafficSource(
        string $medium,
        ?string $source,
        ?string $referrerUrl,
        ?string $referrerHost,
        ?string $campaign = null,
        ?string $campaignContent = null,
        ?string $searchTerms = null,
    ): static {
        $this->medium          = $medium;
        $this->source          = $source;
        $this->referrerUrl     = $referrerUrl;
        $this->referrerHost    = $referrerHost;
        $this->campaign        = $campaign;
        $this->campaignContent = $campaignContent;
        $this->searchTerms     = $searchTerms;

        return $this;
    }

    public function getBrowser(): ?string
    {
        return $this->browser;
    }

    public function getBrowserVersion(): ?string
    {
        return $this->browserVersion;
    }

    public function getOs(): ?string
    {
        return $this->os;
    }

    public function getOsVersion(): ?string
    {
        return $this->osVersion;
    }

    public function getDeviceType(): ?string
    {
        return $this->deviceType;
    }

    /**
     * The visitor's browser, operating system and device
     */
    public function setClient(
        ?string $browser,
        ?string $browserVersion,
        ?string $os,
        ?string $osVersion,
        ?string $deviceType,
        ?string $userAgent,
    ): static {
        $this->browser        = $browser;
        $this->browserVersion = $browserVersion;
        $this->os             = $os;
        $this->osVersion      = $osVersion;
        $this->deviceType     = $deviceType;
        $this->userAgent      = $userAgent;

        return $this;
    }

    public function getScreenResolution(): ?string
    {
        return $this->screenResolution;
    }

    public function setScreenResolution(?string $screenResolution): static
    {
        $this->screenResolution = $screenResolution;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }
}
