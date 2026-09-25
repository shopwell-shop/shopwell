---
title: Fix PHP 8.2 deprecations
issue: NEXT-29164
---
# Core
* Added missing properties to entities so that dynamically created property deprecations are not raised
* Changed instances of deprecated function `utf8_encode` to use appropriate alternatives
* Changed plugin zip utilities to not open potentially empty zip files
* Changed `\Shopwell\Core\Framework\Struct\ArrayEntity` so that translations are correctly implemented with partial entities
* Changed `\Shopwell\Tests\Integration\Core\Checkout\Cart\CartPersisterTest::testCartCanBeUnserialized` so that it uses a fixture without removed properties
