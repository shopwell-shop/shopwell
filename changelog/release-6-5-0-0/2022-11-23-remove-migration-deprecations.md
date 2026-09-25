---
title: Remove Migration deprecations
issue: NEXT-21203
---
# Core
* Changed all `MigrationSteps` to be `@internal`
* Removed all Migrations in old migration namespaces, all migrations are now in a namespace specifying the major version, where they were added.
* Deprecated `\Shopwell\Core\Framework\Migration\MigrationSource::addReplacementPattern()` it will be removed in v6.6.0.0 as it is not used anymore.
* Removed `\Shopwell\Core\Migration\Traits\MigrationUntouchedDbTestTrait` use `\Shopwell\Core\Migration\Test\MigrationUntouchedDbTestTrait` instead.
* Removed deprecated class `\Shopwell\Core\Framework\Migration\Api\MigrationController`.
* Removed deprecated methods in `\Shopwell\Core\Framework\Migration\MigrationStep`.
