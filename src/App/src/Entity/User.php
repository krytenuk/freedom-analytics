<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use FwsDoctrineAuth\Mapping as Auth;

use function in_array;

/**
 * Someone who can log in to view reports. Admins see every site and manage sites and users. Viewers see only the sites
 * they have been given.
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
class User
{
    public const ROLE_ADMIN  = 'admin';
    public const ROLE_VIEWER = 'viewer';

    public const ROLES = [
        self::ROLE_ADMIN  => 'Administrator',
        self::ROLE_VIEWER => 'Viewer',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 254)]
    #[Auth\IdentityProperty]
    private string $email = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Auth\CredentialProperty]
    private string $password = '';

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $role = self::ROLE_VIEWER;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Auth\IsActiveProperty]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /** @var Collection<int, Site> The sites a viewer can see. Admins see every site. */
    #[ORM\ManyToMany(targetEntity: Site::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_sites')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'site_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $sites;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->sites     = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * The password hash
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password A hash from FwsDoctrineAuth\Password\PasswordHasherInterface, never the password itself
     */
    public function setPassword(string $password): static
    {
        $this->password = $password;

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

    public function getDisplayName(): string
    {
        return $this->name !== '' ? $this->name : $this->email;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getRoleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
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
     * @return Collection<int, Site>
     */
    public function getSites(): Collection
    {
        return $this->sites;
    }

    /**
     * @param iterable<Site> $sites
     */
    public function setSites(iterable $sites): static
    {
        $this->sites->clear();
        foreach ($sites as $site) {
            $this->sites->add($site);
        }

        return $this;
    }

    public function canViewSite(Site $site): bool
    {
        return $this->isAdmin() || in_array($site, $this->sites->toArray(), true);
    }
}
