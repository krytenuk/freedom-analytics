<?php

/**
 * krytenuk/doctrine-auth settings that are safe to commit. The throttle secret belongs in local.php.
 */

declare(strict_types=1);

use App\Entity\User;

return [
    'doctrine-auth' => [
        // User carries the module's identity, credential and active attributes
        'user_class' => User::class,
        // Where AuthenticationMiddleware sends people who are not logged in
        'login_path' => '/login',
        'app_2fa'    => [
            // Shown next to the codes in the user's authenticator app
            'issuer' => 'Freedom Analytics',
        ],
    ],
];
