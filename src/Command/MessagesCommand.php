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
 * Class MessagesCommand.
 *
 * @package Drupal\migrate_tools
 */
class MessagesCommand extends Command {

  use ContainerAwareCommandTrait;
  use MigrateCommandTrait;

  /**
   * {@inheritdoc}
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function configure() {
    $this
      ->setName('migrate:messages')
      ->setDescription($this->trans('commands.migrate.messages.description'));
    $this->addCommonArguments();
    $this->addOption('csv', '', InputOption::VALUE_NONE, 'print as a csv');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new DrupalStyle($input, $output);

    $migrationIds = $this->getMigrationIds($input);
    $options = $this->buildOptionList($input, ['csv']);
    foreach ($migrationIds as $migrationId) {
      $this->processMessages($migrationId, $options, $io);
    }

  }

  /**
   * @param                                   $migrationId
   * @param                                   $options
   * @param \Drupal\Console\Style\DrupalStyle $io
   */
  private function processMessages($migrationId, $options, DrupalStyle $io) {
    /** @var MigrationInterface $migration */
    $migration = \Drupal::service('plugin.manager.migration')
                        ->createInstance($migrationId);//TODO - di
    if ($migration) {
      $table = $this->getTable($migration);
      if (empty($table)) {
        $io->simple("No messages for migration {$migrationId}");
      } else if (array_key_exists('csv', $options)) {
        foreach ($table as $row) {
          fputcsv(STDOUT, $row);
        }
      } else {
        $io->simple($migrationId);
        $io->table(array_shift($table), $table);
      }
    } else {
      $io->warning("Migration {$migrationId} does not exist");
    }
  }

  /**
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   * @return array
   */
  private function getTable(MigrationInterface $migration) {
    $map = $migration->getIdMap();
    $first = TRUE;
    $table = [];
    foreach ($map->getMessageIterator() as $row) {
      unset($row->msgid);
      if ($first) {
        // @todo: Ideally, replace sourceid* with source key names. Or, should
        // getMessageIterator() do that?
        foreach ($row as $column => $value) {
          $table[0][] = $column;
        }
        $first = FALSE;
      }
      $table[] = (array) $row;
    }
    return $table;
  }
}
