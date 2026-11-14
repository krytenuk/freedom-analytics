<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use FwsDoctrineAuth\Password\PasswordHasherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_keys;
use function filter_var;
use function implode;
use function in_array;
use function is_string;
use function mb_strlen;
use function sprintf;
use function strtolower;
use function trim;

use const FILTER_VALIDATE_EMAIL;

/**
 * Adds a user, or sets the password of one that exists. Use it to make the first administrator:
 *
 *   vendor/bin/laminas analytics:user --email=you@example.com --name="Your Name" --role=admin
 */
final class CreateUserCommand extends Command
{
    public const NAME = 'analytics:user';

    public const MIN_PASSWORD_LENGTH = 10;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PasswordHasherInterface $hasher,
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Adds a user who can log in, or sets the password of an existing one')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Their email address, which they log in with')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Their name', '')
            ->addOption(
                'role',
                null,
                InputOption::VALUE_REQUIRED,
                'One of: ' . implode(', ', array_keys(User::ROLES)),
                User::ROLE_ADMIN
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getOption('email');
        $email = is_string($email) && $email !== '' ? $email : $io->ask('Email address');
        $email = strtolower(trim((string) $email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $io->error('Please give a valid email address.');

            return self::FAILURE;
        }

        $role = (string) $input->getOption('role');
        if (! in_array($role, array_keys(User::ROLES), true)) {
            $io->error(sprintf('The role must be one of: %s.', implode(', ', array_keys(User::ROLES))));

            return self::FAILURE;
        }

        $password = (string) $io->askHidden('Password (at least ' . self::MIN_PASSWORD_LENGTH . ' characters)');
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $io->error('The password is too short.');

            return self::FAILURE;
        }
        if ($password !== (string) $io->askHidden('Password again')) {
            $io->error('The passwords do not match.');

            return self::FAILURE;
        }

        $user  = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $isNew = ! $user instanceof User;
        if ($isNew) {
            $user = (new User())->setEmail($email)->setRole($role);
            $this->entityManager->persist($user);
        }

        $name = (string) $input->getOption('name');
        if ($name !== '') {
            $user->setName($name);
        }

        $user->setPassword($this->hasher->hash($password))->setActive(true);
        $this->entityManager->flush();

        $io->success($isNew ? sprintf('Added %s as %s.', $email, $user->getRoleLabel()) : sprintf(
            'Set the password of %s.',
            $email
        ));

        return self::SUCCESS;
    }
}
