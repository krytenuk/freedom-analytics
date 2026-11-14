<?php

declare(strict_types=1);

namespace Admin\Handler;

use Mezzio\Csrf\CsrfGuardInterface;
use Mezzio\Csrf\CsrfMiddleware;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function array_filter;
use function ceil;
use function is_array;
use function is_string;
use function max;
use function mb_check_encoding;
use function sprintf;

trait RequestDataTrait
{
    /**
     * Drops anything that is not a valid string, such as email[]=x, as a form can neither validate nor redisplay it.
     *
     * @return array<string, string>
     */
    private function getPostData(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        /** @var array<string, string> $data */
        $data = array_filter(
            is_array($body) ? $body : [],
            static fn (mixed $value): bool => is_string($value) && mb_check_encoding($value, 'UTF-8')
        );

        return $data;
    }

    /**
     * The address the request came from, for doctrine-auth's failed login limits. This is the address that connected to
     * the web server. Forwarded headers are ignored, as anyone can set them, which would let them get around the limits
     * or block someone else. Behind a proxy or load balancer, add middleware that trusts only that proxy's header.
     */
    private function getClientIp(ServerRequestInterface $request): string
    {
        $address = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($address) && $address !== '' ? $address : '0.0.0.0';
    }

    private function getSession(ServerRequestInterface $request): SessionInterface
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        if (! $session instanceof SessionInterface) {
            throw new RuntimeException('SessionMiddleware must run before ' . self::class);
        }

        return $session;
    }

    private function getCsrfGuard(ServerRequestInterface $request): CsrfGuardInterface
    {
        $guard = $request->getAttribute(CsrfMiddleware::GUARD_ATTRIBUTE);
        if (! $guard instanceof CsrfGuardInterface) {
            throw new RuntimeException('CsrfMiddleware must run before ' . self::class);
        }

        return $guard;
    }

    /**
     * @param array{throttled: string, throttled-no-time: string} $messages
     */
    private function throttledMessage(?int $retryAfter, array $messages): string
    {
        if ($retryAfter === null) {
            return $messages['throttled-no-time'];
        }

        return sprintf($messages['throttled'], max(1, (int) ceil($retryAfter / 60)));
    }
}
