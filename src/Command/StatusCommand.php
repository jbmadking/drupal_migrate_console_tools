<?php

namespace Drupal\migrate_console_tools\Command;


use Drupal\Console\Command\Shared\ContainerAwareCommandTrait;
use Drupal\Console\Style\DrupalStyle;
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
                     'Only return names, not all the details (faster)');
  }

  /**
   * {@inheritdoc}
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
   * @param       $groupId
   * @param array $migrationList
   * @param       $nameOnly
   * @param       $io
   */
  private function processGroup($groupId,
                                array $migrationList,
                                $nameOnly,
                                $io) {

    call_user_func_array([$io, 'table'],
                         $nameOnly ?
                           $this->processNameOnly($groupId, $migrationList) :
                           $this->processFullInfo($groupId, $migrationList));

  }

  /**
   * @param       $groupId
   * @param array $migrationList
   * @return array
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
   * @param $groupId
   * @return string
   */
  private function getGroupName($groupId) {
    $group = MigrationGroup::load($groupId);
    return !empty($group) ? "{$group->label()} ({$group->id()})" : $groupId;
  }

  /**
   * @param $groupId
   * @param $migrationList
   * @return array
   */
  private function processFullInfo($groupId, $migrationList) {

    $rows = [];
    /**
     * @var  $migration_id string
     * @var  $migration    MigrationInterface
     */
    foreach ($migrationList as $migrationId => $migration) {
      $rows[] = $this->getRow($migrationId, $migration);
    }
    return [$this->getHeader($groupId), $rows];
  }

  /**
   * @param $groupId
   * @return array
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
   * @param                                           $migrationId
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   * @return array
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
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   * @return int|string
   */
  private function getSourceRows(MigrationInterface $migration) {

    try {
      $source_plugin = $migration->getSourcePlugin();
      $source_rows = $source_plugin->count();
    } catch (\Exception $e) {
      $source_rows = -1;
    } catch (\Throwable $t) {
      //php 7 only
      $source_rows = -1;
    }
    // -1 indicates uncountable sources.
    return ($source_rows === -1) ? 'N/A' : $source_rows;
  }

  /**
   * @param string                                       $sourceRows
   * @param \Drupal\migrate\Plugin\MigrateIdMapInterface $map
   * @return string
   */
  private function getUnprocessed($sourceRows, MigrateIdMapInterface $map) {
    return ($sourceRows === 'N/A') ? $sourceRows :
      $sourceRows - $map->processedCount();
  }

  /**
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   * @return string
   */
  private function getLastImported(MigrationInterface $migration) {
    $store = \Drupal::keyValue('migrate_last_imported');
    $lastImported = $store->get($migration->id(), FALSE);
    $date_formatter = \Drupal::service('date.formatter');//TODO - inject this
    return $lastImported ? $date_formatter->format($lastImported / 1000,
                                                   'custom',
                                                   'Y-m-d H:i:s') : '';
  }


}
