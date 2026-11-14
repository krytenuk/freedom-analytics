<?php

declare(strict_types=1);

namespace Admin\Model;

use App\Entity\Site;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use FwsDoctrineAuth\Password\PasswordHasherInterface;

use function in_array;
use function strtolower;

/**
 * Lists, finds, saves and deletes the users who can log in
 */
class UserModel
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PasswordHasherInterface $hasher,
    ) {
    }

    public function find(int $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    /**
     * @return list<User>
     */
    public function findAll(): array
    {
        /** @var list<User> $result */
        $result = $this->entityManager->getRepository(User::class)->findBy([], ['name' => 'ASC', 'email' => 'ASC']);

        return $result;
    }

    public function isEmailTaken(string $email, ?User $except = null): bool
    {
        $found = $this->entityManager->getRepository(User::class)->findOneBy(['email' => strtolower($email)]);

        return $found !== null && $found !== $except;
    }

    /**
     * @param array<string, mixed> $values The form's filtered values. The password is only changed when one is given.
     * @param list<Site> $sites The sites a viewer can see
     */
    public function save(User $user, array $values, array $sites): void
    {
        $user
            ->setName((string) $values['name'])
            ->setEmail(strtolower((string) $values['email']))
            ->setRole((string) $values['role'])
            ->setActive((bool) ($values['active'] ?? false))
            ->setSites($user->getRole() === User::ROLE_ADMIN ? [] : $sites);

        $password = (string) ($values['password'] ?? '');
        if ($password !== '') {
            $user->setPassword($this->hasher->hash($password));
        }

        if ($user->getId() === null) {
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();
    }

    public function verifyPassword(User $user, string $password): bool
    {
        return $this->hasher->verify($password, $user->getPassword());
    }

    public function changePassword(User $user, string $password): void
    {
        $user->setPassword($this->hasher->hash($password));
        $this->entityManager->flush();
    }

    public function saveProfile(User $user, string $name): void
    {
        $user->setName($name);
        $this->entityManager->flush();
    }

    /**
     * Why a user cannot be deleted, made a viewer or turned off, or null if they can be: there must always be an
     * active admin, and nobody can do it to themselves
     */
    public function whyNotRemovable(User $user, User $currentUser): ?string
    {
        if ($user === $currentUser) {
            return 'You cannot remove your own access.';
        }

        if ($user->isAdmin() && $user->isActive() && $this->countActiveAdmins() <= 1) {
            return 'This is the only active administrator.';
        }

        return null;
    }

    /**
     * Whether saving these values would leave no active admin
     *
     * @param array<string, mixed> $values
     */
    public function wouldRemoveLastAdmin(User $user, array $values): bool
    {
        if (! $user->isAdmin() || ! $user->isActive() || $user->getId() === null) {
            return false;
        }

        $staysAdmin = ($values['role'] ?? null) === User::ROLE_ADMIN && in_array(
            $values['active'] ?? false,
            [true, '1', 1],
            true
        );

        return ! $staysAdmin && $this->countActiveAdmins() <= 1;
    }

    public function delete(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    private function countActiveAdmins(): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.role = :role')
            ->andWhere('u.active = true')
            ->setParameter('role', User::ROLE_ADMIN)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
