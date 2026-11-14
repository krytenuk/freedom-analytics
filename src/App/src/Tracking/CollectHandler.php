<?php

declare(strict_types=1);

namespace App\Tracking;

use App\Entity\Site;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function base64_decode;
use function explode;
use function hash_hmac;
use function is_array;
use function is_string;
use function json_decode;
use function parse_str;
use function parse_url;
use function preg_match;
use function strtoupper;
use function substr;
use function trim;

use const PHP_URL_HOST;

/**
 * The tracking endpoint, /collect, which the tracker (public/fa.js) sends page views and events to. GET requests, from
 * the tracker's image fallback, get a transparent 1x1 GIF. POST requests, from navigator.sendBeacon(), get 204.
 *
 * The answer is the same whether or not the hit was recorded, so the endpoint does not reveal which site keys exist.
 * The X-Analytics header says why a hit was left out, for debugging. It never uses the session, so tracked sites'
 * visitors get no cookie from this server: the tracker keeps the visitor id in a first party cookie instead.
 */
final class CollectHandler implements RequestHandlerInterface
{
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    private const CORS_HEADERS = [
        'Access-Control-Allow-Origin'  => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type',
        'Access-Control-Max-Age'       => '86400',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HitParser $parser,
        private readonly HitRecorder $recorder,
        private readonly UserAgentParser $userAgents,
        private readonly TrackingConfig $config,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new EmptyResponse(204, self::CORS_HEADERS);
        }

        try {
            $status = $this->collect($request);
        } catch (Throwable $exception) {
            // A tracking failure must never break the tracked site
            $this->logger?->error('Tracking hit failed: ' . $exception->getMessage(), ['exception' => $exception]);
            $status = 'error';
        }

        return $this->respond($request, $status);
    }

    /**
     * Records the hit, or returns why it was left out
     */
    private function collect(ServerRequestInterface $request): string
    {
        $hit = $this->parser->parse($this->params($request));
        if ($hit === null) {
            return 'invalid';
        }

        $site = $this->entityManager->getRepository(Site::class)->findOneBy(['siteKey' => $hit->siteKey]);
        if (! $site instanceof Site || ! $site->isActive()) {
            return 'unknown-site';
        }

        $host = parse_url($hit->url, PHP_URL_HOST);
        if ($site->isCheckDomain() && (! is_string($host) || ! $site->acceptsHost($host))) {
            return 'wrong-domain';
        }

        if (
            $site->isRespectDoNotTrack()
            && ($request->getHeaderLine('DNT') === '1' || $request->getHeaderLine('Sec-GPC') === '1')
        ) {
            return 'do-not-track';
        }

        $userAgent = $request->getHeaderLine('User-Agent');
        $client    = $this->userAgents->parse($userAgent);
        if ($client->isBot) {
            return 'robot';
        }

        $ip = $this->clientIp($request);
        if ($ip !== null && IpAddress::matches($ip, $site->getExcludedIpList())) {
            return 'excluded-ip';
        }

        $now = new DateTimeImmutable('now', $this->config->timezone);

        $this->recorder->record(
            $site,
            $hit,
            $hit->visitorKey ?? $this->cookielessVisitorKey($site, $ip, $userAgent, $now),
            $client,
            $userAgent === '' ? null : $userAgent,
            $ip === null ? null : ($this->config->anonymizeIp ? IpAddress::anonymize($ip) : $ip),
            $this->country($request),
            $now,
        );

        return 'ok';
    }

    /**
     * The hit's parameters, from the query string, or from a POST body that is form encoded or JSON
     *
     * @return array<string, mixed>
     */
    private function params(ServerRequestInterface $request): array
    {
        if ($request->getMethod() !== 'POST') {
            return $request->getQueryParams();
        }

        $body = $request->getParsedBody();
        if (is_array($body) && $body !== []) {
            return $body;
        }

        // sendBeacon() with a string sends text/plain, which PHP does not parse
        $raw = trim((string) $request->getBody());
        if ($raw === '') {
            return $request->getQueryParams();
        }

        if ($raw[0] === '{') {
            $json = json_decode($raw, true);

            return is_array($json) ? $json : [];
        }

        parse_str($raw, $parsed);

        return $parsed;
    }

    /**
     * Where the request came from. Forwarded headers are only used when the ip_header setting names one, as anyone can
     * set them; set it only behind a proxy that sets the header itself.
     */
    private function clientIp(ServerRequestInterface $request): ?string
    {
        if ($this->config->ipHeader !== null) {
            $forwarded = trim(explode(',', $request->getHeaderLine($this->config->ipHeader))[0]);
            if (IpAddress::isValid($forwarded)) {
                return $forwarded;
            }
        }

        $address = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($address) && IpAddress::isValid($address) ? $address : null;
    }

    private function country(ServerRequestInterface $request): ?string
    {
        if ($this->config->countryHeader === null) {
            return null;
        }

        $country = strtoupper(trim($request->getHeaderLine($this->config->countryHeader)));

        // Cloudflare sends XX for unknown and T1 for Tor
        return preg_match('~^[A-Z]{2}$~', $country) === 1 && $country !== 'XX' ? $country : null;
    }

    /**
     * For visitors whose browsers block the tracker's cookie: a hash of the site, the day, and the visitor's address
     * and browser. It changes every day, so it cannot follow them from day to day, and the address is not stored in it.
     */
    private function cookielessVisitorKey(
        Site $site,
        ?string $ip,
        string $userAgent,
        DateTimeImmutable $now,
    ): string {
        $data = $site->getSiteKey() . '|' . $now->format('Y-m-d') . '|' . $ip . '|' . $userAgent;

        return 'h' . substr(hash_hmac('sha256', $data, $this->config->visitorHashSecret . 'freedom-analytics'), 0, 31);
    }

    private function respond(ServerRequestInterface $request, string $status): ResponseInterface
    {
        $headers = self::CORS_HEADERS + [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'X-Analytics'   => $status,
        ];

        if ($request->getMethod() === 'POST') {
            return new EmptyResponse(204, $headers);
        }

        $response = new Response('php://memory', 200, $headers + ['Content-Type' => 'image/gif']);
        $response->getBody()->write((string) base64_decode(self::GIF));

        return $response;
    }
}
