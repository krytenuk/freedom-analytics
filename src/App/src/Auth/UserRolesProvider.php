<?php

declare(strict_types=1);

namespace App\Auth;

use App\Entity\User;
use FwsDoctrineAuth\Mezzio\RolesProviderInterface;

/**
 * Gives doctrine-auth each user's role, so it is on the logged-in Mezzio\Authentication\UserInterface
 */
final class UserRolesProvider implements RolesProviderInterface
{
    public function getRoles(object $user): array
    {
        return $user instanceof User ? [$user->getRole()] : [];
    }
}
