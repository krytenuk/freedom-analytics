<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function hash;

/**
 * A page of a site, by its address without the fragment or campaign parameters. Like Open Web Analytics' documents,
 * page views refer to it, so the address is stored once. It is found by a hash of the address, as the address can be
 * too long to index.
 */
#[ORM\Entity]
#[ORM\Table(name: 'pages')]
#[ORM\UniqueConstraint(name: 'uniq_pages_site_hash', columns: ['site_id', 'url_hash'])]
class Page
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(name: 'url_hash', type: Types::STRING, length: 40, options: ['fixed' => true])]
    private string $urlHash;

    #[ORM\Column(type: Types::TEXT)]
    private string $url;

    /** The path and query, which the reports show */
    #[ORM\Column(type: Types::STRING, length: 768)]
    private string $path;

    /** The title the page last had */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(name: 'first_seen_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $firstSeenAt;

    public function __construct(Site $site, string $url, string $path, DateTimeImmutable $now)
    {
        $this->site        = $site;
        $this->url         = $url;
        $this->urlHash     = self::hashUrl($url);
        $this->path        = $path;
        $this->firstSeenAt = $now;
    }

    public static function hashUrl(string $url): string
    {
        return hash('sha1', $url);
    }

    public function getId(): ?int
    {
        return $this->id === null ? null : (int) $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getFirstSeenAt(): DateTimeImmutable
    {
        return $this->firstSeenAt;
    }
}
