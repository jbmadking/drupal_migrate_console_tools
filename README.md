#Migrate Console Tools

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/ibrows/drupal_migrate_console_tools/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/ibrows/drupal_migrate_console_tools/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/ibrows/drupal_migrate_console_tools/badges/build.png?b=master)](https://scrutinizer-ci.com/g/ibrows/drupal_migrate_console_tools/build-status/master)

Migrate Console Tools module is a port of the Migrate Tools module for the Drupal Console project.

Migrate console tools only ports the commands from migrate_tools to Drupal Console and does not include any of the UI elements. It is currently not on the roadmap to add the ui elements.
Note that these commands do not support drush style --simulate support

    migrate:status - Lists migrations and their status.
    migrate:import - Performs import operations.
    migrate:rollback - Performs rollback operations.
    migrate:stop - Cleanly stops a running operation.
    migrate:reset - Sets a migration status to Idle if it's gotten stuck.
    migrate:messages - Lists any messages associated with a migration import.
    migrate:fields-source - Gets the names of migrate source fields

Development

We use Github pull request workflow. Each pull request should have a related and cross-linked drupal.org issue.

https://github.com/drupal-media/entity_browser
Contributions

We welcome contributions in the following areas

- Additional migrate commands - if you have a good drupal console migrate command we want to include it
- Unit tests - as this is a port it currently has no testing, we would like to change this
- Refactoring - only minimal refactoring has been done so far. Further refactoring would be happily accepted

