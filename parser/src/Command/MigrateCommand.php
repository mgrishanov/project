<?php

declare(strict_types=1);

namespace WB\Parser\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WB\Parser\Database\ClickHouseConnection;
use WB\Parser\Migration\MigrationManager;
use WB\Parser\Util\Logger;

/**
 * Команда для управления миграциями ClickHouse
 */
#[AsCommand(
    name: 'migrate',
    description: 'Manage ClickHouse migrations',
)]
class MigrateCommand extends Command
{
    private Logger $logger;

    protected function configure(): void
    {
        $this
            ->addOption(
                'create',
                'c',
                InputOption::VALUE_REQUIRED,
                'Create a new migration with the given name'
            )
            ->addOption(
                'rollback',
                'r',
                InputOption::VALUE_NONE,
                'Rollback the last batch of migrations'
            )
            ->addOption(
                'reset',
                null,
                InputOption::VALUE_NONE,
                'Reset all migrations'
            )
            ->addOption(
                'refresh',
                null,
                InputOption::VALUE_NONE,
                'Reset and re-run all migrations'
            )
            ->addOption(
                'status',
                's',
                InputOption::VALUE_NONE,
                'Show migration status'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->logger = new Logger('migrate-command');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('ClickHouse Migration Manager');

        try {
            $io->section('Initializing ClickHouse client');
            $client = ClickHouseConnection::getClient();
            $migrationManager = new MigrationManager($client);

            // Создание новой миграции
            if ($name = $input->getOption('create')) {
                $io->section('Creating new migration');
                $path = $migrationManager->create($name);
                $io->success("Migration created: {$path}");
                return Command::SUCCESS;
            }

            // Отображение статуса миграций
            if ($input->getOption('status')) {
                $io->section('Migration status');
                
                $appliedMigrations = $migrationManager->getAppliedMigrations();
                $availableMigrations = $migrationManager->getAvailableMigrations();
                $pendingMigrations = array_diff($availableMigrations, $appliedMigrations);
                
                $io->table(
                    ['Migration', 'Status'],
                    array_merge(
                        array_map(fn($m) => [$m, 'Applied'], $appliedMigrations),
                        array_map(fn($m) => [$m, 'Pending'], $pendingMigrations)
                    )
                );
                
                return Command::SUCCESS;
            }

            // Сброс всех миграций
            if ($input->getOption('reset')) {
                $io->section('Resetting all migrations');
                
                if (!$io->confirm('This will reset ALL migrations. Are you sure?', false)) {
                    $io->warning('Reset canceled');
                    return Command::SUCCESS;
                }
                
                $rolledBack = $migrationManager->reset();
                
                if (empty($rolledBack)) {
                    $io->note('No migrations to reset');
                } else {
                    $io->success('Migrations reset successfully');
                    $io->listing($rolledBack);
                }
                
                return Command::SUCCESS;
            }

            // Обновление миграций (сброс и повторное применение)
            if ($input->getOption('refresh')) {
                $io->section('Refreshing all migrations');
                
                if (!$io->confirm('This will reset and re-run ALL migrations. Are you sure?', false)) {
                    $io->warning('Refresh canceled');
                    return Command::SUCCESS;
                }
                
                $applied = $migrationManager->refresh();
                
                if (empty($applied)) {
                    $io->note('No migrations to refresh');
                } else {
                    $io->success('Migrations refreshed successfully');
                    $io->listing($applied);
                }
                
                return Command::SUCCESS;
            }

            // Откат последней партии миграций
            if ($input->getOption('rollback')) {
                $io->section('Rolling back last batch of migrations');
                
                $rolledBack = $migrationManager->rollback();
                
                if (empty($rolledBack)) {
                    $io->note('No migrations to rollback');
                } else {
                    $io->success('Migrations rolled back successfully');
                    $io->listing($rolledBack);
                }
                
                return Command::SUCCESS;
            }

            // По умолчанию - применение миграций
            $io->section('Running migrations');
            
            $applied = $migrationManager->migrate();
            
            if (empty($applied)) {
                $io->note('No migrations to run');
            } else {
                $io->success('Migrations completed successfully');
                $io->listing($applied);
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Migration failed: ' . $e->getMessage());
            $this->logger->error('Migration failed', ['error' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }
}
