<?php

namespace Drupal\migrate_tools\Command;

use Drupal\Console\Command\Shared\ContainerAwareCommandTrait;
use Drupal\Console\Style\DrupalStyle;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_tools\ConsoleLogMigrateMessage;
use Drupal\migrate_tools\MigrateExecutable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ImportCommand.
 *
 * @package Drupal\migrate_tools
 */
class ImportCommand extends Command {

  use ContainerAwareCommandTrait;

  use MigrateCommandTrait;

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('migrate:import')
      ->setDescription($this->trans('commands.migrate.import.description'));
    $this->addCommonOptions();
    $this->addOption('all',
                     '',
                     InputOption::VALUE_NONE,
                     'Process all migrations.');
    $this->addOption('limit',
                     '',
                     InputOption::VALUE_REQUIRED,
                     'Limit on the number of items to process in each migration');
    $this->addOption('feedback',
                     '',
                     InputOption::VALUE_REQUIRED,
                     'Frequency of progress messages, in items processed');
    $this->addOption('idlist',
                     '',
                     InputOption::VALUE_REQUIRED,
                     'Comma-separated list of IDs to import');
    $this->addOption('update',
                     '',
                     InputOption::VALUE_NONE,
                     'In addition to processing unprocessed items from the source, update previously-imported items with the current data');
    $this->addOption('force',
                     '',
                     InputOption::VALUE_NONE,
                     'Force an operation to run, even if all dependencies are not satisfied');
    $this->addOption('execute-dependencies',
                     '',
                     InputOption::VALUE_NONE,
                     'Execute all dependent migrations first');
    $this->addOption('migration',
                     '',
                     InputOption::VALUE_REQUIRED,
                     'ID of migration(s) to import. Delimit multiple using commas.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new DrupalStyle($input, $output);

    $group_names = $input->getOption('group');
    $tag_names = $input->getOption('tag');
    $all = $input->getOption('all');
    $migrationNames = $this->getMigrationIds($input);

    if (!$all && !$group_names && !$migrationNames && !$tag_names) {
      $io->error('You must specify --all, --group, --tag or one or more migration names separated by commas');
      return;
    }

    $migrations = $this->migrationList($migrationNames);
    if (empty($migrations)) {
      drush_log(dt('No migrations found.'), 'error');
    }

    $options = $this->buildOptionList($input);
    $options['logger'] = new ConsoleLogMigrateMessage($io);

    // Take it one group at a time, importing the migrations within each group.
    foreach ($migrations as $group_id => $migration_list) {
      array_walk($migration_list, [$this, 'executeMigration'], $options);
    }

    $io->info($this->trans('commands.migrate.import.messages.success'));
  }

  private function buildOptionList(InputInterface $input) {

    $options = [];
    foreach ([
               'limit',
               'feedback',
               'idlist',
               'update',
               'force',
               'execute-dependencies',
             ] as $option) {
      if ($input->getOption($option)) {
        $options[$option] = $input->getOption($option);
      }
    }
    return $options;
  }

  /**
   * Executes a single migration. If the --execute-dependencies option was
   * given, the migration's dependencies will also be executed first.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *  The migration to execute.
   * @param string                                    $migration_id
   *  The migration ID (not used, just an artifact of array_walk()).
   * @param array                                     $options
   *  Additional options for the migration.
   * @throws \Drupal\migrate\MigrateException
   */
  private function executeMigration(MigrationInterface $migration,
                                    $migration_id,
                                    array $options = []) {
    $log = $options['logger'];
    $required_IDS = $migration->get('requirements');
    if (array_key_exists('execute-dependencies', $options) && $required_IDS) {
      $manager = \Drupal::service('plugin.manager.config_entity_migration');//TODO inject
      $required_migrations = $manager->createInstances($required_IDS);
      $dependency_options = array_merge($options, ['is_dependency' => TRUE]);
      array_walk($required_migrations, __FUNCTION__, $dependency_options);
    }
    if (!empty($options['force'])) {
      $migration->set('requirements', []);
    }
    if (!empty($options['update'])) {
      $migration->getIdMap()->prepareUpdate();
    }
    $executable = new MigrateExecutable($migration, $log, $options);
    $executable->import();
    // drush_op() provides --simulate support
    //drush_op([$executable, 'import']);
  }

}
