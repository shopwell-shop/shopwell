---
title: Mark DemoData and StaticAnalysis namespaces as internal
issue: NEXT-23541
---
# Core
* Deprecated all classes in `Shopwell\Core\DevOps\StaticAnalyze` and `Shopwell\Core\DevOps\DemoData` namespaces, those classes will be internal in v6.5.0.0.
* Deprecated `\Shopwell\Core\Migration\Traits\MigrationUntouchedDbTestTrait`, this trait will be removed, use `\Shopwell\Core\Migration\Test\MigrationUntouchedDbTestTrait` instead.
