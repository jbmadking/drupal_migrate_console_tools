<?php

namespace Drupal\migrate_console_tools\Command;

use Drupal\Console\Core\Command\Shared\ContainerAwareCommandTrait;
use Drupal\Console\Core\Style\DrupalStyle;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\Entity\MigrationGroup;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class StatusCommand.
 *
 * @package Drupal\migrate_tools
 */
class StatusCommand extends Command {

  use ContainerAwareCommandTrait;
  use MigrateCommandTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function configure() {
    $this
      ->setName('migrate:status')
      ->setDescription($this->trans('commands.migrate.status.description'));

    $this->addCommonArguments();
    $this->addCommonOptions();
    $this->addOption('names-only',
                     '',
                     InputOption::VALUE_NONE,
                     $this->trans('commands.migrate.status.options.names-only'));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new DrupalStyle($input, $output);

    $migrations = $this->migrationList($input);
    $nameOnly = $input->getOption('names-only');

    // Take it one group at a time, listing the migrations within each group.
    foreach ($migrations as $groupId => $migration_list) {
      $this->processGroup($groupId, $migration_list, $nameOnly, $io);
    }

  }

  /**
   * Process the group.
   *
   * @param string $groupId
   *    The group ID.
   * @param array $migrationList
   *    A list of migrations.
   * @param string $nameOnly
   *    If set then only output the name, which is faster.
   * @param DrupalStyle $io
   *    Console style io.
   */
  private function processGroup($groupId,
                                array $migrationList,
                                $nameOnly,
                                DrupalStyle $io) {

    call_user_func_array([$io, 'table'],
                         $nameOnly ?
                           $this->processNameOnly($groupId, $migrationList) :
                           $this->processFullInfo($groupId, $migrationList));

  }

  /**
   * Process only the name.
   *
   * @param string $groupId
   *    The group ID.
   * @param array $migrationList
   *    A list of migrations.
   *
   * @return array
   *    The data to print.
   */
  private function processNameOnly($groupId, array $migrationList) {

    $header = [
      'Group: ' . $this->getGroupName($groupId),
    ];

    $rows = [];
    foreach ($migrationList as $migration_id => $migration) {
      $rows[] = [$migration_id];
    }

    return [$header, $rows];

  }

  /**
   * Get the name of the group.
   *
   * @param string $groupId
   *    The group ID.
   *
   * @return string
   *    The group name.
   */
  private function getGroupName($groupId) {
    $group = MigrationGroup::load($groupId);
    return !empty($group) ? "{$group->label()} ({$group->id()})" : $groupId;
  }

  /**
   * Process the full info.
   *
   * @param string $groupId
   *    The group ID.
   * @param array $migrationList
   *    A list of migrations.
   *
   * @return array
   *    The full data.
   */
  private function processFullInfo($groupId, array $migrationList) {

    $rows = [];

    /*
     * @var  $migrationId
     * @var  $migration
     */
    foreach ($migrationList as $migrationId => $migration) {
      $rows[] = $this->getRow($migrationId, $migration);
    }
    return [$this->getHeader($groupId), $rows];
  }

  /**
   * Get the table header.
   *
   * @param string $groupId
   *   The group ID.
   *
   * @return array
   *   An array of strings.
   */
  private function getHeader($groupId) {

    return [
      'Group: ' . $this->getGroupName($groupId),
      'Status',
      'Total',
      'Imported',
      'Unprocessed',
      'Last imported',
    ];

  }

  /**
   * Get a row.
   *
   * @param string $migrationId
   *   The ID of the migration.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration interface.
   *
   * @return array
   *   The row data.
   */
  private function getRow($migrationId, MigrationInterface $migration) {

    $map = $migration->getIdMap();
    $sourceRows = $this->getSourceRows($migration);

    return [
      $migrationId,
      $migration->getStatusLabel(),
      $sourceRows,
      $map->importedCount(),
      $this->getUnprocessed($sourceRows, $migration->getIdMap()),
      $this->getLastImported($migration),
    ];
  }

  /**
   * Get the source rows.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration interface.
   *
   * @return int|string
   *   The source rows.
   */
  private function getSourceRows(MigrationInterface $migration) {

    try {
      $source_plugin = $migration->getSourcePlugin();
      $source_rows = $source_plugin->count();
    }
    catch (\Exception $e) {
      $source_rows = -1;
    }
    catch (\Throwable $t) {
      // Php 7 only.
      $source_rows = -1;
    }
    // -1 indicates uncountable sources.
    return ($source_rows === -1) ? 'N/A' : $source_rows;
  }

  /**
   * Get unprocessed.
   *
   * @param string $sourceRows
   *   The source rows.
   * @param \Drupal\migrate\Plugin\MigrateIdMapInterface $map
   *   The migrate interface.
   *
   * @return string
   *   The unprocessed data.
   */
  private function getUnprocessed($sourceRows, MigrateIdMapInterface $map) {
    return ($sourceRows === 'N/A') ? $sourceRows :
      $sourceRows - $map->processedCount();
  }

  /**
   * Get the last imported.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration interface.
   *
   * @return string
   *   The last imported.
   */
  private function getLastImported(MigrationInterface $migration) {
    $store = \Drupal::keyValue('migrate_last_imported');
    $lastImported = $store->get($migration->id(), FALSE);
    // TODO - inject this.
    $date_formatter = \Drupal::service('date.formatter');
    return $lastImported ? $date_formatter->format($lastImported / 1000,
                                                   'custom',
                                                   'Y-m-d H:i:s') : '';
  }

}
