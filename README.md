MIGRATE CONSOLE TOOLS
---------------------

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/ibrows/drupal_migrate_console_tools/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/ibrows/drupal_migrate_console_tools/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/ibrows/drupal_migrate_console_tools/badges/build.png?b=master)](https://scrutinizer-ci.com/g/ibrows/drupal_migrate_console_tools/build-status/master)


CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Recommended modules
 * Installation
 * Configuration
 * Maintainers
 * Contributing

INTRODUCTION
------------

This module ports Migrate Tools module commands to Drupal Console.

This module only ports the commands & does not include any UI elements.
It is currently not on the roadmap to add the ui elements.

Note that these commands do not support drush style --simulate

    migrate:status - Lists migrations and their status.
    migrate:import - Performs import operations.
    migrate:rollback - Performs rollback operations.
    migrate:stop - Cleanly stops a running operation.
    migrate:reset - Sets a migration status to Idle if it's gotten stuck.
    migrate:messages - Lists any messages associated with a migration import.
    migrate:fields-source - Gets the names of migrate source fields

REQUIREMENTS
------------

* [Migrate](https://www.drupal.org/project/migrate)
* [Migrate Plus](https://www.drupal.org/project/migrate_plus)

INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. See:
   https://drupal.org/documentation/install/modules-themes/modules-7
   for further information.

CONFIGURATION
-------------

No configuration necessary - install and use with Drupal Console

MAINTAINERS
-----------

[Tom Whiston](https://www.drupal.org/u/tomw-0)

CONTRIBUTING
------------

We use a Github pull request workflow.
Each pull request should have a related and cross-linked drupal.org issue.

[github](https://github.com/ibrows/drupal_migrate_console_tools)

We welcome contributions in the following areas

* Additional migrate commands - if you have a good drupal console migrate command we want to include it
* Unit tests - as this is a port it currently has no testing, we would like to change this
* Refactoring - only minimal refactoring has been done so far. Further refactoring would be happily accepted
