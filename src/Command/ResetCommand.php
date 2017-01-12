<?php

namespace Drupal\migrate_tools\Command;

use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Drupal\Console\Command\Shared\ContainerAwareCommandTrait;
use Drupal\Console\Style\DrupalStyle;

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
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new DrupalStyle($input, $output);

    $migrationIds = $this->getMigrationIds($input);

    foreach ($migrationIds as $migrationId) {
      $this->processReset($migrationId, $io);
    }

   // $io->info($this->trans('commands.migrate.reset-status.messages.success'));
  }

  private function processReset($migrationId, DrupalStyle $io){
    /** @var MigrationInterface $migration */
    $migration = \Drupal::service('plugin.manager.migration')->createInstance($migrationId);//TODO - di
    if ($migration) {
      $status = $migration->getStatus();
      if ($status === MigrationInterface::STATUS_IDLE) {
        $io->warning("Migration {$migrationId} is already Idle");
      }
      else {
        $migration->setStatus(MigrationInterface::STATUS_IDLE);
        $io->success("Migration {$migrationId} reset to Idle");
      }
    }
    else {
      $io->error("Migration {$migrationId} does not exist");
    }

  }
}
