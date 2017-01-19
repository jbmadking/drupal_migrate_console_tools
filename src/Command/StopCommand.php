<?php

namespace Drupal\migrate_console_tools\Command;

use Drupal\Console\Command\Shared\ContainerAwareCommandTrait;
use Drupal\Console\Style\DrupalStyle;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class StopCommand.
 *
 * @package Drupal\migrate_tools
 */
class StopCommand extends Command {

  use ContainerAwareCommandTrait;
  use MigrateCommandTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function configure() {
    $this
      ->setName('migrate:stop')
      ->setDescription($this->trans('commands.migrate.stop.description'));
    $this->addCommonArguments();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new DrupalStyle($input, $output);

    $migrationIds = $this->getMigrationIds($input);

    foreach ($migrationIds as $migrationId) {
      $this->processStop($migrationId, $io);
    }

  }

  /**
   * Process a stop command.
   *
   * @param string $migrationId
   *   The migration Id.
   * @param \Drupal\Console\Style\DrupalStyle $io
   *   Drupal Console style io.
   */
  private function processStop($migrationId, DrupalStyle $io) {

    // TODO - DI.
    /** @var MigrationInterface $migration */
    $migration = \Drupal::service('plugin.manager.migration')
      ->createInstance($migrationId);
    if ($migration) {
      $status = $migration->getStatus();
      switch ($status) {
        case MigrationInterface::STATUS_IDLE:
          $io->warning("Migration {$migrationId} is Idle");
          break;

        case MigrationInterface::STATUS_DISABLED:
          $io->warning("Migration {$migrationId} is Disabled");
          break;

        case MigrationInterface::STATUS_STOPPING:
          $io->warning("Migration {$migrationId} is already stopping");
          break;

        default:
          $migration->interruptMigration(MigrationInterface::RESULT_STOPPED);
          $io->warning("Migration {$migrationId} requested to stop");
          break;
      }
    }
    else {
      $io->error("Migration {$migrationId} does not exist");
    }
  }

}
