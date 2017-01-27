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
   *
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function configure() {
    $this
      ->setName('migrate:messages')
      ->setDescription($this->trans('commands.migrate.messages.description'));
    $this->addCommonArguments();
    $this->addOption('csv',
                     '',
                     InputOption::VALUE_NONE,
                     $this->trans('commands.migrate.import.messages.csv'));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
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
   * Get the messages for the migration.
   *
   * @param string $migrationId
   *   The migration id.
   * @param array $options
   *   An array of options (◔_◔).
   * @param \Drupal\Console\Style\DrupalStyle $io
   *   Drupal style io.
   */
  private function processMessages($migrationId,
                                   array $options,
                                   DrupalStyle $io) {
    // TODO - di.
    /** @var MigrationInterface $migration */
    $migration = \Drupal::service('plugin.manager.migration')
      ->createInstance($migrationId);
    if ($migration) {
      $table = $this->getTable($migration);
      if (empty($table)) {
        $io->simple("No messages for migration {$migrationId}");
      }
      elseif (array_key_exists('csv', $options)) {
        foreach ($table as $row) {
          fputcsv(STDOUT, $row);
        }
      }
      else {
        $io->simple($migrationId);
        $io->table(array_shift($table), $table);
      }
    }
    else {
      $io->warning("Migration {$migrationId} does not exist");
    }
  }

  /**
   * Make a table of messages from a migration.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration interface.
   *
   * @return array
   *    A display table.
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
