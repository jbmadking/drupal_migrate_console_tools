<?php

namespace Drupal\migrate_console_tools\Command;

use Drupal\Console\Core\Command\Shared\ContainerAwareCommandTrait;
use Drupal\Console\Core\Style\DrupalStyle;
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
   *
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function configure() {
    $this
      ->setName('migrate:rollback')
      ->setDescription($this->trans('commands.migrate.rollback.description'));
    $this->addCommonArguments();
    $this->addCommonOptions();
    $this->addAllOption();
    $this->addOption('feedback',
                     '',
                     InputOption::VALUE_REQUIRED,
                     $this->trans('commands.migrate.rollback.option.feedback'));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new DrupalStyle($input, $output);

    $migrationIds = $this->getMigrationIds($input);

    $options = $this->buildOptionList($input,
                                      ['group', 'tag', 'all', 'feedback']);

    $options['logger'] = new ConsoleLogMigrateMessage($io);

    if (empty($migrationIds) &&
        !$this->testForRequiredKeys(['all', 'group', 'tag'], $options)
    ) {
      $io->warning('You must specify --all, --group, or one or more migration names separated by commas');
      return;
    }

    $migrations = $this->migrationList($input);
    if (empty($migrations)) {
      $io->error('No migrations found');
      return;
    }

    // Take it one group at a time rolling back the migrations within each.
    foreach ($migrations as $migration_list) {
      // Roll back in reverse order.
      $migration_list = array_reverse($migration_list);
      foreach ($migration_list as $migrationId => $migration) {
        $executable = new MigrateExecutable($migration,
                                            $options['logger'],
                                            $options);
        try {
          $executable->rollback();
        }
        catch (\Exception $e) {
          $options['logger']->display("exception when rolling back {$migrationId} : {$e->getMessage()} : You must clean-up/reset this migration",
                                      'error');
        }
        catch (\Throwable $e) {
          $options['logger']->display("exception when rolling back {$migrationId} : {$e->getMessage()} : You must clean-up/reset this migration",
                                      'error');
        }
      }
    }

  }

}
