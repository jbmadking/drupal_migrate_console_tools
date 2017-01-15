<?php

namespace Drupal\migrate_console_tools\Command;

use Drupal\Console\Command\Shared\ContainerAwareCommandTrait;
use Drupal\Console\Style\DrupalStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
   *
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function configure() {
    $this
      ->setName('migrate:fields-source')
      ->setDescription($this->trans('commands.migrate.fields-source.description'));
    $this->addCommonArguments();
    $this->addCommonOptions();
    $this->addAllOption();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new DrupalStyle($input, $output);

    $migrationIds = $this->migrationList($input);

    /** @var \Drupal\migrate\Plugin\MigrationInterface[] $migration */
    foreach ($migrationIds as $type => $migration) {
      $io->block($type);
      foreach ($migration as $plugin) {
        $source = $plugin->getSourcePlugin();
        $table = [];
        foreach ($source->fields() as $machine_name => $description) {
          $table[] = [strip_tags($description), $machine_name];
        }
        $io->table(array_shift($table), $table);
      }
    }
  }

}
