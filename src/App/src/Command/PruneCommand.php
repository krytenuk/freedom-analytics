<?php

declare(strict_types=1);

namespace App\Command;

use App\Tracking\TrackingConfig;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * Deletes visits, page views and events older than the retention period, and the visitors and pages left with none.
 * Run it from cron, such as once a night:
 *
 *   vendor/bin/laminas analytics:prune
 */
final class PruneCommand extends Command
{
    public const NAME = 'analytics:prune';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TrackingConfig $config,
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Deletes analytics data older than the retention period')
            ->addOption(
                'days',
                null,
                InputOption::VALUE_REQUIRED,
                'Keep this many days, instead of the retention_days setting'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $days = $input->getOption('days') !== null ? (int) $input->getOption('days') : $this->config->retentionDays;
        if ($days < 1) {
            $io->note('Nothing to do: retention_days is 0, which keeps everything. Pass --days to prune anyway.');

            return self::SUCCESS;
        }

        $before = (new DateTimeImmutable('today', $this->config->timezone))
            ->sub(new DateInterval(sprintf('P%dD', $days)));

        $connection = $this->entityManager->getConnection();
        $params     = ['before' => $before];
        $types      = ['before' => Types::DATE_IMMUTABLE];

        // Page views and events go with their visits, as the foreign keys cascade
        $visits = $connection->executeStatement('DELETE FROM visits WHERE visit_date < :before', $params, $types);

        $visitors = $connection->executeStatement(
            'DELETE FROM visitors WHERE NOT EXISTS (SELECT 1 FROM visits WHERE visits.visitor_id = visitors.id)'
        );
        $pages    = $connection->executeStatement(
            'DELETE FROM pages WHERE NOT EXISTS (SELECT 1 FROM pageviews WHERE pageviews.page_id = pages.id)'
            . ' AND NOT EXISTS (SELECT 1 FROM events WHERE events.page_id = pages.id)'
        );

        $io->success(sprintf(
            'Deleted %d visits from before %s, %d visitors and %d pages.',
            $visits,
            $before->format('j M Y'),
            $visitors,
            $pages
        ));

        return self::SUCCESS;
    }
}
