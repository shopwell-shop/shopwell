---
title: Fix RetryableTransaction missing savepoint error
issue: https://github.com/shopwell-shop/shopwell/issues/9024
---
# Core
* Changed `\Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction` to process additional exceptions.
  Added code uses reflection to reset \Doctrine\DBAL\Connection::$transactionNestingLevel when transactions is
  rolled back by MySQL. Changed should be rolled back when https://github.com/doctrine/dbal/issues/6651 is fixed.


