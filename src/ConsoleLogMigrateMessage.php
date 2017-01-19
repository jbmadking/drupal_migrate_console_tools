<?php

namespace Drupal\migrate_console_tools;

use Drupal\Console\Core\Style\DrupalStyle;
use Drupal\migrate\MigrateMessageInterface;

/**
 * ConsoleLogMigrateMessage.
 *
 * @package Drupal\migrate_console_tools
 */
class ConsoleLogMigrateMessage implements MigrateMessageInterface {

  /**
   * Drupal Console io styling.
   *
   * @var DrupalStyle
   */
  private $io;

  /**
   * DrushLogMigrateMessage constructor.
   *
   * @param \Drupal\Console\Style\DrupalStyle $io
   *    Drupal Console io styling.
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
    // TODO - switch on type.
    $this->io->simple($message);
  }

}
