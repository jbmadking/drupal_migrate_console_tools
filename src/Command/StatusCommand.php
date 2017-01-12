<?php

namespace Drupal\migrate_tools\Command;

use Drupal\Component\Utility\Unicode;
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
 * //TODO - dependency injection
 *
 * @package Drupal\migrate_tools
 */
class StatusCommand extends Command {

  use ContainerAwareCommandTrait;

  /**
   * {@inheritdoc}
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function configure() {
    $this
      ->setName('migrate:status')
      ->setDescription($this->trans('commands.migrate.status.description'));

    $this->addOption('group',
                     '',
                     InputOption::VALUE_REQUIRED,
                     'A comma-separated list of migration groups to list');
    $this->addOption('tag',
                     '',
                     InputOption::VALUE_REQUIRED,
                     'Name of the migration tag to list');
    $this->addOption('names-only',
                     '',
                     InputOption::VALUE_NONE,
                     'Only return names, not all the details (faster)');
    $this->addOption('migration',
                     '',
                     InputOption::VALUE_OPTIONAL,
                     'Restrict to a comma-separated list of migrations.');
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

    $io->info($this->trans('commands.migrate.status.messages.success'));
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
      //TODO - comment out before release
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


  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @return array
   */
  protected function migrationList(InputInterface $input) {
    // Filter keys must match the migration configuration property name.
    $migrationIds = $this->getMigrationIds($input);

    $manager = \Drupal::service('plugin.manager.config_entity_migration');//TODO - inject
    $plugins = $manager->createInstances([]);

    $matchedMigrations = $this->getMatchedMigrations($migrationIds, $plugins);

    $matchedMigrations = $this->filterMigrations($migrationIds,
                                                 $matchedMigrations,
                                                 $this->getFilter($input));

    $migrations = $this->sortMigrations($matchedMigrations);

    return isset($migrations) ? $migrations : [];
  }

  private function getMigrationIds(InputInterface $input) {
    return $input->getOption('migration') ?
      explode(',', $input->getOption('migration')) : [];
  }

  private function getFilter(InputInterface $input) {
    $filter = [];
    $filter['migration_group'] = $input->getOption('group') ?
      explode(',', $input->getOption('group')) : [];
    $filter['migration_tags'] = $input->getOption('tag') ?
      explode(',', $input->getOption('tag')) : [];
    return $filter;
  }

  private function getMatchedMigrations($migrationIds, $plugins) {
    return empty($migrationIds) ? $plugins :
      $this->restrictMigrations($migrationIds, $plugins);
  }

  private function restrictMigrations($migrationIds, $plugins) {
    $matchedMigrations = [];
    // Get the requested migrations.
    $migrationIds = explode(',', Unicode::strtolower($migrationIds));
    foreach ($plugins as $id => $migration) {
      if (in_array(Unicode::strtolower($id), $migrationIds)) {
        $matchedMigrations [$id] = $migration;
      }
    }
    return $matchedMigrations;
  }

  private function filterMigrations($migrationIds,
                                    array $matchedMigrations,
                                    $filter) {
    // Filters the matched migrations if a group or a tag has been input.
    if (!empty($filter['migration_group']) ||
        !empty($filter['migration_tags'])
    ) {
      // Get migrations in any of the specified groups and with any of the
      // specified tags.
      foreach ($filter as $property => $values) {
        $matchedMigrations = $this->runFilter($migrationIds,
                                              $matchedMigrations,
                                              $property,
                                              $values);
      }
    }
    return $matchedMigrations;
  }

  private function runFilter($migrationIds,
                             $matchedMigrations,
                             $property,
                             $values) {

    if (!empty($values)) {
      $filtered_migrations = [];
      foreach ($values as $search_value) {
        /**
         * @var string             $id
         * @var MigrationInterface $migration
         */
        foreach ($matchedMigrations as $id => $migration) {
          // Cast to array because migration_tags can be an array.
          $configured_values = (array) $migration->get($property);
          $configured_id = (in_array($search_value, $configured_values)) ?
            $search_value : 'default';
          if (empty($search_value) || empty($migrationIds) ||
              $search_value === $configured_id ||
              in_array(Unicode::strtolower($id), $migrationIds, FALSE)
          ) {
            $filtered_migrations[$id] = $migration;

          }
        }
      }
      $matchedMigrations = $filtered_migrations;
    }
    return $matchedMigrations;

  }

  private function sortMigrations($matchedMigrations) {

    $migrations = [];
    if (!empty($matchedMigrations)) {
      foreach ($matchedMigrations as $id => $migration) {
        $migrations[$this->getConfiguredGroupId($migration)][$id] = $migration;
      }
    }
    return $migrations;
  }

  private function getConfiguredGroupId($migration) {
    return empty($migration->get('migration_group')) ? 'default' :
      $migration->get('migration_group');
  }

}
