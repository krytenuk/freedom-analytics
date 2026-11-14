<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Site;
use App\Tracking\Hit;
use App\Tracking\HitRecorder;
use App\Tracking\TrackingConfig;
use App\Tracking\UserAgentParser;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_key_first;
use function array_keys;
use function array_rand;
use function array_sum;
use function bin2hex;
use function ctype_digit;
use function max;
use function min;
use function mt_getrandmax;
use function mt_rand;
use function random_bytes;
use function round;
use function sin;
use function sort;
use function sprintf;

use const M_PI;

/**
 * Fills a site with made-up visits, so the reports can be tried out before the tracking code is on a real site.
 * The visits are recorded the same way as real ones, through HitRecorder.
 *
 *   vendor/bin/laminas analytics:demo-data <site key or id> --days=30
 */
final class DemoDataCommand extends Command
{
    public const NAME = 'analytics:demo-data';

    private const PAGES = [
        '/'                     => 'Home',
        '/about'                => 'About Us',
        '/services'             => 'Services',
        '/services/web-design'  => 'Web Design',
        '/services/hosting'     => 'Web Hosting',
        '/blog'                 => 'Blog',
        '/blog/getting-started' => 'Getting Started',
        '/blog/seo-tips'        => 'Ten SEO Tips',
        '/pricing'              => 'Pricing',
        '/contact'              => 'Contact Us',
    ];

    /** Referrers, weighted by how often they appear */
    private const REFERRERS = [
        ''                                                  => 40,
        'https://www.google.com/'                           => 30,
        'https://www.bing.com/search?q=web+design+services' => 5,
        'https://duckduckgo.com/'                           => 4,
        'https://www.facebook.com/'                         => 6,
        'https://t.co/abc123'                               => 3,
        'https://www.linkedin.com/feed/'                    => 3,
        'https://news.ycombinator.com/item?id=1'            => 2,
        'https://www.example-directory.org/listings/web'    => 3,
        'https://blog.partner-site.co.uk/recommended-tools' => 2,
        'utm'                                               => 4,
    ];

    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36'
            => 30,
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 '
            . 'Safari/605.1.15'            => 12,
        'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 '
            . 'Mobile/15E148 Safari/604.1' => 20,
        'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile '
            . 'Safari/537.36'              => 15,
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0' => 8,
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 '
            . 'Safari/537.36 Edg/130.0.0.0'                                                => 9,
        'Mozilla/5.0 (iPad; CPU OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 '
            . 'Mobile/15E148 Safari/604.1'                                                 => 4,
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36' => 2,
    ];

    private const COUNTRIES = [
        'GB' => 50,
        'US' => 20,
        'IE' => 5,
        'DE' => 5,
        'FR' => 5,
        'CA' => 4,
        'AU' => 4,
        'NL' => 3,
        'IN' => 4,
    ];

    private const LANGUAGES = ['en-gb' => 55, 'en-us' => 30, 'de-de' => 5, 'fr-fr' => 5, 'nl-nl' => 5];

    private const SCREENS = [
        '1920x1080' => 30,
        '1536x864'  => 10,
        '2560x1440' => 10,
        '390x844'   => 20,
        '412x915'   => 15,
        '820x1180'  => 5,
        '1440x900'  => 10,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HitRecorder $recorder,
        private readonly UserAgentParser $userAgents,
        private readonly TrackingConfig $config,
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Fills a site with made-up visits, to try the reports out')
            ->addArgument('site', InputArgument::REQUIRED, 'The site key or id')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'How many days back to start', '30')
            ->addOption('visits', null, InputOption::VALUE_REQUIRED, 'About how many visits a day', '40');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $siteId = $this->findSiteId((string) $input->getArgument('site'));
        if ($siteId === null) {
            $io->error('No site has that key or id.');

            return self::FAILURE;
        }

        $days       = max(1, min(400, (int) $input->getOption('days')));
        $perDay     = max(1, min(2000, (int) $input->getOption('visits')));
        $visitors   = [];
        $hits       = 0;
        $visitsMade = 0;
        $today      = new DateTimeImmutable('today', $this->config->timezone);

        $io->progressStart($days);
        for ($day = $days - 1; $day >= 0; $day--) {
            $site = $this->entityManager->find(Site::class, $siteId);
            if (! $site instanceof Site) {
                return self::FAILURE;
            }

            $date = $today->sub(new DateInterval('P' . $day . 'D'));
            // Weekdays are busier, and the traffic grows a little over time
            $count = (int) round($perDay * ((int) $date->format('N') >= 6 ? 0.6 : 1.0)
                * (0.8 + 0.4 * ($days - $day) / $days) * (0.85 + 0.3 * mt_rand() / mt_getrandmax()));

            $times = [];
            for ($i = 0; $i < $count; $i++) {
                // More visits in the daytime
                $hour    = (int) round(13 + 5 * sin(mt_rand() / mt_getrandmax() * 2 * M_PI));
                $times[] = $date->setTime(max(0, min(23, $hour)), mt_rand(0, 59), mt_rand(0, 59));
            }
            sort($times);

            $seenToday = [];
            foreach ($times as $start) {
                if ($day === 0 && $start > new DateTimeImmutable('now', $this->config->timezone)) {
                    continue;
                }

                // About a third of visits are from returning visitors
                $returning = $visitors !== [] && mt_rand(1, 100) <= 30;
                $visitor   = $returning ? $visitors[array_rand($visitors)] : null;
                if ($visitor === null || isset($seenToday[$visitor['key']])) {
                    $visitor    = $this->newVisitor();
                    $visitors[] = $visitor;
                }
                $seenToday[$visitor['key']] = true;

                $hits += $this->visit($site, $visitor, $start);
                $visitsMade++;
            }

            $this->entityManager->clear();
            $io->progressAdvance();
        }
        $io->progressFinish();

        $io->success(sprintf('Added %d visits with %d page views and events.', $visitsMade, $hits));

        return self::SUCCESS;
    }

    /**
     * @param array{key: string, ua: string, country: string, language: string, screen: string, ip: string} $visitor
     * @return int The number of hits
     */
    private function visit(Site $site, array $visitor, DateTimeImmutable $time): int
    {
        $referrer = $this->weighted(self::REFERRERS);
        $base     = 'https://' . $site->getDomain();
        $paths    = array_keys(self::PAGES);
        $path     = mt_rand(1, 100) <= 55 ? '/' : $paths[array_rand($paths)];
        $url      = $base . $path;
        if ($referrer === 'utm') {
            $referrer = '';
            $url     .= '?utm_source=newsletter&utm_medium=email&utm_campaign='
                . ['autumn-offer', 'monthly-news', 'black-friday'][mt_rand(0, 2)];
        }

        $client = $this->userAgents->parse($visitor['ua']);
        // Bounces are common; otherwise a few pages
        $pages = mt_rand(1, 100) <= 45 ? 1 : mt_rand(2, 7);
        $hits  = 0;
        for ($i = 0; $i < $pages; $i++) {
            $hit = new Hit(
                Hit::TYPE_PAGEVIEW,
                $site->getSiteKey(),
                $url,
                $visitor['key'],
                self::PAGES[$path] ?? 'Page',
                $i === 0 ? $referrer : $base . '/',
                $visitor['screen'],
                $visitor['language'],
            );
            $this->recorder->record(
                $site,
                $hit,
                $visitor['key'],
                $client,
                $visitor['ua'],
                $visitor['ip'],
                $visitor['country'],
                $time
            );
            $hits++;

            if (mt_rand(1, 100) <= 12) {
                [$category, $action, $label] = [
                    ['Downloads', 'Download', 'brochure.pdf'],
                    ['Forms', 'Submit', 'Contact form'],
                    ['Outbound Links', 'Click', 'https://github.com/'],
                    ['Video', 'Play', 'Intro video'],
                ][mt_rand(0, 3)];
                $this->recorder->record(
                    $site,
                    new Hit(
                        Hit::TYPE_EVENT,
                        $site->getSiteKey(),
                        $url,
                        $visitor['key'],
                        null,
                        null,
                        null,
                        null,
                        $category,
                        $action,
                        $label,
                        null
                    ),
                    $visitor['key'],
                    $client,
                    $visitor['ua'],
                    $visitor['ip'],
                    $visitor['country'],
                    $time->add(new DateInterval('PT5S'))
                );
                $hits++;
            }

            $time = $time->add(new DateInterval('PT' . mt_rand(8, 240) . 'S'));
            $path = $paths[array_rand($paths)];
            $url  = $base . $path;
        }

        return $hits;
    }

    /**
     * @return array{key: string, ua: string, country: string, language: string, screen: string, ip: string}
     */
    private function newVisitor(): array
    {
        return [
            'key'      => bin2hex(random_bytes(12)),
            'ua'       => $this->weighted(self::USER_AGENTS),
            'country'  => $this->weighted(self::COUNTRIES),
            'language' => $this->weighted(self::LANGUAGES),
            'screen'   => $this->weighted(self::SCREENS),
            'ip'       => sprintf('%d.%d.%d.0', mt_rand(11, 220), mt_rand(0, 255), mt_rand(0, 255)),
        ];
    }

    /**
     * @param array<string, int> $weights
     */
    private function weighted(array $weights): string
    {
        $pick = mt_rand(1, (int) array_sum($weights));
        foreach ($weights as $value => $weight) {
            $pick -= $weight;
            if ($pick <= 0) {
                return (string) $value;
            }
        }

        return (string) array_key_first($weights);
    }

    private function findSiteId(string $keyOrId): ?int
    {
        $repository = $this->entityManager->getRepository(Site::class);
        $site       = ctype_digit($keyOrId) ? $repository->find((int) $keyOrId) : null;
        $site     ??= $repository->findOneBy(['siteKey' => $keyOrId]);

        return $site instanceof Site ? $site->getId() : null;
    }
}
