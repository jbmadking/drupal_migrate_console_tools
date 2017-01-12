<?php

namespace Drupal\migrate_tools;

use Drupal\Console\Style\DrupalStyle;
use Drupal\migrate\MigrateMessageInterface;

class ConsoleLogMigrateMessage implements MigrateMessageInterface {

  /** @var  DrupalStyle */
  private $io;
  /**
   * DrushLogMigrateMessage constructor.
   */
  public function __construct(DrupalStyle $io) {
    $this->io = $io;
  }


  /**
   * Output a message from the migration.
   *
   * @param string $message
   *   The message to display.
   * @param string $type
   *   The type of message to display.
   *
   * @see drush_log()
   */
  public function display($message, $type = 'status') {
    //TODO - switch on type
    $this->io->simple($message);
  }

}
