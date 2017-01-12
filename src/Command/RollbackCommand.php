<?php

namespace Drupal\migrate_console_tools\Command;

use Drupal\Console\Command\Shared\ContainerAwareCommandTrait;
use Drupal\Console\Style\DrupalStyle;
use Drupal\migrate_console_tools\ConsoleLogMigrateMessage;
use Drupal\migrate_console_tools\MigrateExecutable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class RollbackCommand.
 *
 * @package Drupal\migrate_tools
 */
class RollbackCommand extends Command {

  use ContainerAwareCommandTrait;
  use MigrateCommandTrait;

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('migrate:rollback')
      ->setDescription($this->trans('commands.migrate.rollback.description'));
    $this->addCommonArguments();
    $this->addCommonOptions();
    $this->addOption('all',
                     '',
                     InputOption::VALUE_NONE,
                     'Process all migrations.');
    $this->addOption('feedback',
                     '',
                     InputOption::VALUE_REQUIRED,
                     'Frequency of progress messages, in items processed');
  }

  /**
   * {@inheritdoc}
   * @throws \Drupal\migrate\MigrateException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new DrupalStyle($input, $output);

    $migrationIds = $this->getMigrationIds($input);

    $options = $this->buildOptionList($input,
                                      ['group', 'tag', 'all', 'feedback']);

    if (!$options['all'] && !$options['group'] && empty($migrationIds) &&
        !$options['tag']
    ) {
      $io->warning('You must specify --all, --group, --tag, or one or more migration names separated by commas');
      return;
    }

    $migrations = $this->migrationList($input);
    if (empty($migrations)) {
      $io->error('No migrations found');
      return;
    }

    // Take it one group at a time, rolling back the migrations within each group.
    foreach ($migrations as $group_id => $migration_list) {
      // Roll back in reverse order.
      $migration_list = array_reverse($migration_list);
      foreach ($migration_list as $migration_id => $migration) {
        $executable = new MigrateExecutable($migration,
                                            new ConsoleLogMigrateMessage($io),
                                            $options);
        $executable->rollback();
        // drush_op() provides --simulate support.
        //drush_op(array($executable, 'rollback'));
      }
    }

    $io->info($this->trans('commands.migrate.rollback.messages.success'));
  }
}
