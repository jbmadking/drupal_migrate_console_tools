<?php

namespace Drupal\migrate_console_tools\Command;

use Drupal\Component\Utility\Unicode;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class MigrateCommandTrait.
 *
 * @package Drupal\migrate_tools\Command
 */
trait MigrateCommandTrait {

  /**
   * Build the options list.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *    The input interface.
   * @param string[] $inputOptions
   *   An array of input options.
   *
   * @return array
   *   The build options list.
   *
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function buildOptionList(InputInterface $input,
                                     array $inputOptions) {

    $options = [];
    foreach ($inputOptions as $option) {
      if ($input->getOption($option)) {
        $options[$option] = $input->getOption($option);
      }
      else {
        $options[$option] = NULL;
      }
    }
    return $options;
  }

  /**
   * Add the main argument which is required in every command.
   */
  protected function addCommonArguments() {
    $this->addArgument('migration',
                       InputOption::VALUE_REQUIRED,
                       $this->trans('commands.migrate.shared.arguments.migration'));
  }

  /**
   * Add a common set of options to command.
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
   * Add the All option to a command.
   */
  protected function addAllOption() {
    $this->addOption('all',
                     '',
                     InputOption::VALUE_NONE,
                     $this->trans('commands.migrate.shared.options.all'));
  }

  /**
   * Ensure that this trait is used with a command.
   *
   * @param string $name
   *   The name of the option.
   * @param string $shortcut
   *    The shortcut for the option.
   * @param int|null $mode
   *    InputOption constant.
   * @param string $description
   *    A drescription.
   * @param null|string $default
   *    A default value.
   *
   * @return mixed
   *   Return whatever symfony returns here.
   */
  abstract public function addOption($name,
                                     $shortcut = NULL,
                                     $mode = NULL,
                                     $description = '',
                                     $default = NULL);

  /**
   * Ensure that this trait is used with a command.
   *
   * @param string $name
   *   The name.
   * @param int|null $mode
   *   The mode.
   * @param string $description
   *   The description.
   * @param null|string $default
   *   The default value.
   *
   * @return mixed
   *    Return whatever symfony returns here.
   */
  abstract public function addArgument($name,
                                       $mode = NULL,
                                       $description = '',
                                       $default = NULL);

  /**
   * Test for keys that you need, and return as soon as one is not found.
   *
   * @param string[] $keys
   *    An array of keys.
   * @param array $options
   *    An array of options.
   *
   * @return bool
   *    Test passed true/false.
   */
  protected function testForRequiredKeys(array $keys, array $options) {
    foreach ($keys as $key) {
      if (!array_key_exists($key, $options)) {
        // Return false as soon as a missing key is found.
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Get the migration id's.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   The input interface.
   *
   * @return array
   *    An array of id's.
   *
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function getMigrationIds(InputInterface $input) {
    return $input->getArgument('migration') ?
      explode(',', $input->getArgument('migration')) : [];
  }

  /**
   * Get the migration list.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *    An InputInterface.
   *
   * @return array
   *    The list of migrations.
   *
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function migrationList(InputInterface $input) {
    // Filter keys must match the migration configuration property name.
    $migrationIds = $this->getMigrationIds($input);

    // TODO - inject.
    $manager = \Drupal::service('plugin.manager.config_entity_migration');
    $plugins = $manager->createInstances([]);

    $matchedMigrations = $this->getMatchedMigrations($migrationIds, $plugins);

    $matchedMigrations = $this->filterMigrations($migrationIds,
                                                 $matchedMigrations,
                                                 $this->getFilter($input));

    $migrations = $this->sortMigrations($matchedMigrations);

    return $migrations !== NULL ? $migrations : [];
  }

  /**
   * Get the filter params.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *     An InputInterface.
   *
   * @return array
   *    An array of filter params.
   *
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
   * Get the matched migrations.
   *
   * @param array $migrationIds
   *    An array of migration id's.
   * @param array $plugins
   *    An array of plugins.
   *
   * @return array
   *    An array of matching migrations.
   */
  private function getMatchedMigrations(array $migrationIds, array $plugins) {
    return empty($migrationIds) ? $plugins :
      $this->restrictMigrations($migrationIds, $plugins);
  }

  /**
   * Restrict the migrations based on the plugins.
   *
   * @param array $migrationIds
   *    An array of migration id's.
   * @param array $plugins
   *    An array of plugins.
   *
   * @return array
   *    An array of migrations.
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
   * Filter the migrations.
   *
   * @param array $migrationIds
   *    An array of migration id's.
   * @param array $matchedMigrations
   *    An array of migration id's.
   * @param array $filter
   *    An array of mfilter options.
   *
   * @return array
   *    An array of migration id's.
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
   * Run the filtering.
   *
   * @param array $migrationIds
   *    An array of migration id's.
   * @param array $matchedMigrations
   *    An array of migration id's.
   * @param string $property
   *    A property string.
   * @param array $values
   *    An array of values.
   *
   * @return array
   *    An array of migration id's.
   */
  private function runFilter(array $migrationIds,
                             array $matchedMigrations,
                             $property,
                             array $values) {

    if (!empty($values)) {
      $filtered_migrations = [];
      foreach ($values as $search_value) {
        /*
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
   * Sort the migrations.
   *
   * @param array $matchedMigrations
   *    An array of migration id's.
   *
   * @return array
   *    An array of migration id's.
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
   * Get the configured group ID.
   *
   * @param MigrationInterface $migration
   *    A migration interface.
   *
   * @return string
   *    The group id.
   */
  private function getConfiguredGroupId(MigrationInterface $migration) {
    return empty($migration->get('migration_group')) ? 'default' :
      $migration->get('migration_group');
  }

}
