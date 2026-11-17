<?php

declare(strict_types=1);

namespace App\Command;

use App\Tracking\GeoIpUpdater;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_string;

/**
 * Downloads the country database for looking up visitors' countries when no country header comes with a hit. Run it
 * from cron, such as once a week:
 *
 *   vendor/bin/laminas analytics:geoip-update
 */
final class GeoIpUpdateCommand extends Command
{
    public const NAME = 'analytics:geoip-update';

    public function __construct(private readonly GeoIpUpdater $updater)
    {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Downloads the country database used when hits have no country header')
            ->addOption(
                'source',
                null,
                InputOption::VALUE_REQUIRED,
                'maxmind (needs maxmind_account_id and maxmind_licence_key) or dbip; by default maxmind when they are'
                . ' set, otherwise dbip'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $option = $input->getOption('source');
        $source = is_string($option) && $option !== '' ? $option : $this->updater->defaultSource();

        try {
            $database = $this->updater->update($source, new DateTimeImmutable());
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());

            return self::FAILURE;
        }

        $io->success('Installed ' . $database . '.');

        return self::SUCCESS;
    }
}
