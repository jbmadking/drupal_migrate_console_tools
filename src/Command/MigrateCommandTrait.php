<?php
/**
 * Created by PhpStorm.
 * User: twhiston
 * Date: 12/01/17
 * Time: 20:18
 */

namespace Drupal\migrate_tools\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

trait MigrateCommandTrait {

  protected function addCommonOptions(){
    $this->addOption('group',
                     '',
                     InputOption::VALUE_REQUIRED,
                     'A comma-separated list of migration groups to list');
    $this->addOption('tag',
                     '',
                     InputOption::VALUE_REQUIRED,
                     'Name of the migration tag to list');
  }

  protected function getMigrationIds(InputInterface $input) {
    return $input->getOption('migration') ?
      explode(',', $input->getOption('migration')) : [];
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