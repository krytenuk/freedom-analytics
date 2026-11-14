<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function array_filter;
use function array_map;
use function array_values;
use function bin2hex;
use function explode;
use function preg_split;
use function random_bytes;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

/**
 * A website being tracked. Its tracking code identifies it by its public site key, so the database id is never shown
 * on the tracked site.
 */
#[ORM\Entity]
#[ORM\Table(name: 'sites')]
#[ORM\UniqueConstraint(name: 'uniq_sites_site_key', columns: ['site_key'])]
class Site
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    /** Public, so the tracking code can name the site */
    #[ORM\Column(name: 'site_key', type: Types::STRING, length: 32)]
    private string $siteKey;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name = '';

    /** The site's host name, such as www.example.com, without the scheme */
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $domain = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Whether tracking requests are refused unless the page is on the domain or one of its subdomains */
    #[ORM\Column(name: 'check_domain', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $checkDomain = true;

    /** Whether visitors whose browsers send "Do Not Track" are left out */
    #[ORM\Column(name: 'respect_dnt', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $respectDoNotTrack = true;

    /** IP addresses to leave out, such as the office, one per line or separated by commas */
    #[ORM\Column(name: 'excluded_ips', type: Types::TEXT, nullable: true)]
    private ?string $excludedIps = null;

    /** Inactive sites keep their reports, but stop collecting */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'sites')]
    private Collection $users;

    public function __construct()
    {
        $this->siteKey   = self::generateKey();
        $this->createdAt = new DateTimeImmutable();
        $this->users     = new ArrayCollection();
    }

    public static function generateKey(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSiteKey(): string
    {
        return $this->siteKey;
    }

    public function setSiteKey(string $siteKey): static
    {
        $this->siteKey = $siteKey;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): static
    {
        $this->domain = strtolower(trim($domain));

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description === '' ? null : $description;

        return $this;
    }

    public function isCheckDomain(): bool
    {
        return $this->checkDomain;
    }

    public function setCheckDomain(bool $checkDomain): static
    {
        $this->checkDomain = $checkDomain;

        return $this;
    }

    public function isRespectDoNotTrack(): bool
    {
        return $this->respectDoNotTrack;
    }

    public function setRespectDoNotTrack(bool $respectDoNotTrack): static
    {
        $this->respectDoNotTrack = $respectDoNotTrack;

        return $this;
    }

    public function getExcludedIps(): ?string
    {
        return $this->excludedIps;
    }

    public function setExcludedIps(?string $excludedIps): static
    {
        $this->excludedIps = $excludedIps === '' ? null : $excludedIps;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getExcludedIpList(): array
    {
        $parts = preg_split('/[\s,]+/', (string) $this->excludedIps) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $ip): bool => $ip !== ''));
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    /**
     * Whether a page on this host belongs to the site: the domain itself, or one of its subdomains. A leading "www."
     * on the domain is ignored, so www.example.com also accepts example.com and shop.example.com.
     */
    public function acceptsHost(string $host): bool
    {
        $host   = strtolower(trim($host, '. '));
        $domain = $this->domain;
        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }
        // Allow a domain entered with a port, as for local development
        $domain = explode(':', $domain)[0];

        return $domain !== '' && ($host === $domain || str_ends_with($host, '.' . $domain));
    }
}
