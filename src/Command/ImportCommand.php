<?php

namespace Drupal\migrate_console_tools\Command;

use Drupal\Console\Command\Shared\ContainerAwareCommandTrait;
use Drupal\Console\Style\DrupalStyle;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_console_tools\ConsoleLogMigrateMessage;
use Drupal\migrate_console_tools\MigrateExecutable;
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
    $this->addCommonArguments();
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
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new DrupalStyle($input, $output);


    $migrationNames = $this->getMigrationIds($input);

    $options = $this->buildOptionList($input,
                                      [
                                        'limit',
                                        'feedback',
                                        'idlist',
                                        'update',
                                        'force',
                                        'execute-dependencies',
                                        'group',
                                        'tag',
                                        'all',
                                      ]);


    if ($this->testForRequiredKeys(['group','all','tag'],$options) && empty($migrationNames)) {
      $io->error('You must specify --all, --group, --tag or one or more migration names separated by commas');
      return;
    }

    $migrations = $this->migrationList($input);
    if (empty($migrations)) {
      $io->warning('No migrations found');
      return;
    }

    $options['logger'] = new ConsoleLogMigrateMessage($io);

    // Take it one group at a time, importing the migrations within each group.
    foreach ($migrations as $migration_list) {
      array_walk($migration_list, [$this, 'executeMigration'], $options);
    }

  }


  /**
   * Executes a single migration. If the --execute-dependencies option was
   * given, the migration's dependencies will also be executed first.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *  The migration to execute.
   * @param string                                    $migrationId
   *  The migration ID (not used, just an artifact of array_walk()).
   * @param array                                     $options
   *  Additional options for the migration.
   * @throws \Drupal\migrate\MigrateException
   */
  private function executeMigration(MigrationInterface $migration,
                                    $migrationId,
                                    array $options = []) {
    /** @var MigrateMessageInterface $log */
    $log = $options['logger'];
    $requiredIds = $migration->get('requirements');
    if (array_key_exists('execute-dependencies', $options) && $requiredIds) {
      $manager = \Drupal::service('plugin.manager.config_entity_migration');//TODO inject
      $required_migrations = $manager->createInstances($requiredIds);
      $dependency_options = array_merge($options, ['is_dependency' => TRUE]);
      array_walk($required_migrations,
                 [$this, 'executeMigration'],
                 $dependency_options);
    }
    if (!empty($options['force'])) {
      $migration->set('requirements', []);
    }
    if (!empty($options['update'])) {
      $migration->getIdMap()->prepareUpdate();
    }
    $executable = new MigrateExecutable($migration, $log, $options);
    try {
      $executable->import();
    } catch (\Exception $e) {
      $log->display("exception when importing {$migrationId} : {$e->getMessage()} : You must clean-up/reset this migration",
                    'error');
    } catch (\Throwable $e) {
      $log->display("exception when importing {$migrationId} : {$e->getMessage()} : You must clean-up/reset this migration",
                    'error');
    }
  }

}
