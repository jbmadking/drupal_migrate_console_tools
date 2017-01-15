<?php

namespace Drupal\migrate_console_tools\Command;

use Drupal\Component\Utility\Unicode;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class MigrateCommandTrait
 *
 * @package Drupal\migrate_tools\Command
 */
trait MigrateCommandTrait {

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param string[]                                        $inputOptions
   *
   * @return array
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function buildOptionList(InputInterface $input,
                                     array $inputOptions) {

    $options = [];
    foreach ($inputOptions as $option) {
      if ($input->getOption($option)) {
        $options[$option] = $input->getOption($option);
      } else {
        $options[$option] = NULL;
      }
    }
    return $options;
  }

  /**
   * Add the main argument which is required in every command
   */
  protected function addCommonArguments() {
    $this->addArgument('migration',
                       InputOption::VALUE_REQUIRED,
                       $this->trans('commands.migrate.shared.arguments.migration'));
  }

  /**
   * Add a common set of options to command
   */
  protected function addCommonOptions() {
    $this->addOption('group',
                     '',
                     InputOption::VALUE_REQUIRED,
                     $this->trans('commands.migrate.shared.options.group'));
    $this->addOption('tag',
                     '',
                     InputOption::VALUE_REQUIRED,
                     $this->trans('commands.migrate.shared.options.tag'));
  }

  /**
   * Add the All option to a command
   */
  protected function addAllOption() {
    $this->addOption('all',
                     '',
                     InputOption::VALUE_NONE,
                     $this->trans('commands.migrate.shared.options.all'));
  }

  /**
   * Ensure that this trait is used with a command
   *
   * @param string      $name
   * @param string      $shortcut
   * @param int|null    $mode
   *                    InputOption constant
   * @param string      $description
   * @param null|string $default
   *
   * @return mixed
   */
  abstract public function addOption($name,
                                     $shortcut = NULL,
                                     $mode = NULL,
                                     $description = '',
                                     $default = NULL);

  /**
   * Ensure that this trait is used with a command
   *
   * @param string      $name
   * @param int|null    $mode
   * @param string      $description
   * @param null|string $default
   *
   * @return mixed
   */
  abstract public function addArgument($name,
                                       $mode = NULL,
                                       $description = '',
                                       $default = NULL);

  /**
   * Test for keys that you need, and return as soon as one is not found
   *
   * @param string[] $keys
   * @param array    $options
   *
   * @return bool
   */
  protected function testForRequiredKeys(array $keys, array $options) {
    foreach ($keys as $key) {
      if (!array_key_exists($key, $options)) {
        //return false as soon as a missing key is found
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return array
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function getMigrationIds(InputInterface $input) {
    return $input->getArgument('migration') ?
      explode(',', $input->getArgument('migration')) : [];
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return array
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
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

    return $migrations !== NULL ? $migrations : [];
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return array
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  private function getFilter(InputInterface $input) {
    $filter = [];
    $filter['migration_group'] = $input->getOption('group') ?
      explode(',', $input->getOption('group')) : [];
    $filter['migration_tags'] = $input->getOption('tag') ?
      explode(',', $input->getOption('tag')) : [];
    return $filter;
  }

  /**
   * @param array $migrationIds
   * @param array $plugins
   *
   * @return array
   */
  private function getMatchedMigrations(array $migrationIds, array $plugins) {
    return empty($migrationIds) ? $plugins :
      $this->restrictMigrations($migrationIds, $plugins);
  }

  /**
   * @param array $migrationIds
   * @param array $plugins
   *
   * @return array
   */
  private function restrictMigrations(array $migrationIds, array $plugins) {
    $matchedMigrations = [];
    // Get the requested migrations.
    foreach ($plugins as $id => $migration) {
      if (in_array(Unicode::strtolower($id), $migrationIds)) {
        $matchedMigrations[$id] = $migration;
      }
    }
    return $matchedMigrations;
  }

  /**
   * @param array $migrationIds
   * @param array $matchedMigrations
   * @param array $filter
   *
   * @return array
   */
  private function filterMigrations(array $migrationIds,
                                    array $matchedMigrations,
                                    array $filter) {
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

  /**
   * @param array  $migrationIds
   * @param array  $matchedMigrations
   * @param string $property
   * @param array  $values
   *
   * @return array
   */
  private function runFilter(array $migrationIds,
                             array $matchedMigrations,
                             $property,
                             array $values) {

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
          $configured_id = in_array($search_value, $configured_values) ?
            $search_value : 'default';
          if (empty($search_value) || $search_value === $configured_id ||
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

  /**
   * @param array $matchedMigrations
   *
   * @return array
   */
  private function sortMigrations(array $matchedMigrations) {

    $migrations = [];
    if (!empty($matchedMigrations)) {
      foreach ($matchedMigrations as $id => $migration) {
        $migrations[$this->getConfiguredGroupId($migration)][$id] = $migration;
      }
    }
    return $migrations;
  }

  /**
   * @param MigrationInterface $migration
   *
   * @return string
   */
  private function getConfiguredGroupId(MigrationInterface $migration) {
    return empty($migration->get('migration_group')) ? 'default' :
      $migration->get('migration_group');
  }

}
