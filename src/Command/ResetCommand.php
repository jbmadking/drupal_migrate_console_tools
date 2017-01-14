<?php

namespace Drupal\migrate_console_tools\Command;

use Drupal\Console\Command\Shared\ContainerAwareCommandTrait;
use Drupal\Console\Style\DrupalStyle;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ResetCommand.
 *
 * @package Drupal\migrate_tools
 */
class ResetCommand extends Command {

  use ContainerAwareCommandTrait;

  use MigrateCommandTrait;

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('migrate:reset')
      ->setDescription($this->trans('commands.migrate.reset-status.description'));
    $this->addCommonArguments();
    $this->addCommonOptions();
    $this->addOption('all',
                     '',
                     InputOption::VALUE_NONE,
                     'Process all migrations.');
  }

  /**
   * {@inheritdoc}
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new DrupalStyle($input, $output);

    $options = $this->buildOptionList($input,
                                      ['group', 'all']);

    $migrationIds = $this->migrationList($input);

    if (!$this->testForRequiredKeys(['all', 'group'], $options) && empty($migrationIds)
    ) {
      $io->warning('You must specify --all, --group, or one or more migration names separated by commas');
      return;
    }

    foreach ($migrationIds as $migrationId) {
      foreach ($migrationId as $migration) {
        $this->processReset($migration, $io);
      }
    }

  }

  /**
   * @param MigrationInterface                $migration
   * @param \Drupal\Console\Style\DrupalStyle $io
   */
  private function processReset($migration, DrupalStyle $io) {
    if ($migration) {
      $status = $migration->getStatus();
      if ($status === MigrationInterface::STATUS_IDLE) {
        $io->note("Migration {$migration->id()} is already Idle");
      } else {
        $migration->setStatus(MigrationInterface::STATUS_IDLE);
        $io->success("Migration {$migration->id()} reset to Idle");
      }
    } else {
      $io->error("Migration {$migration->id()} does not exist");
    }

  }
}
