<?php

namespace Drupal\migrate_console_tools\Command;

use Drupal\Console\Core\Command\Shared\ContainerAwareCommandTrait;
use Drupal\Console\Core\Style\DrupalStyle;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class FieldsSourceCommand.
 *
 * @package Drupal\migrate_console_tools
 */
class FieldsSourceCommand extends Command {

  use ContainerAwareCommandTrait;
  use MigrateCommandTrait;

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('migrate:fields-source')
      ->setDescription($this->trans('commands.migrate.fields-source.description'));
    $this->addCommonArguments();
    $this->addCommonOptions();
    $this->addOption('all',
                     '',
                     InputOption::VALUE_NONE,
                     'Process all migrations.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new DrupalStyle($input, $output);

    $migrationIds = $this->migrationList($input);

    /** @var MigrationInterface[] $migration */
    foreach ($migrationIds as $type => $migration) {
      $io->block($type);
      foreach ($migration as $id => $plugin) {
        $source = $plugin->getSourcePlugin();
        $table = [];
        foreach ($source->fields() as $machine_name => $description) {
          $table[] = [strip_tags($description), $machine_name];
        }
        $io->table(array_shift($table),$table);
      }
    }

  }
}
