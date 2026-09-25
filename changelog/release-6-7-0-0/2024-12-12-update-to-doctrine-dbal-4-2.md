---
title: Update to doctrine/dbal:4.2
issue: NEXT-39353
---
# Core
* Changed composer dependency version of `doctrine/dbal` from `^3.9` to `^4.2`
* Removed method `Shopwell\Core\Migration\Test\NullConnection::executeUpdate`
* Removed method `Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException::databasePlatformInvalid`
* Changed `Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\LastIdQuery::__construct` and `Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\OffsetQuery::__construct` to accept `Shopwell\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder` instead of `Doctrine\DBAL\Query\QueryBuilder`
* Changed `Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery::getQuery` to return `Shopwell\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder` instead of `Doctrine\DBAL\Query\QueryBuilder`
* Changed `Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery::execute` return type to int|string
* Changed `Shopwell\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface::matchException` parameter and return type to `\Throwable` instead of `\Exception`. All relevant implementations have been updated.
* Changed `Shopwell\Core\Framework\DataAbstractionLayer\Search\Parser\ParseResult::addParameter` to accept `Doctrine\DBAL\ParameterType|\Doctrine|Dbal\ArrayParameterType` as `$type` and use `ParameterType::STRING` as default value
* Changed `Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue` to accept `Doctrine\DBAL\ParameterType` as parameter type and to use prepared statements under the hood
* Added `Shopwell\Core\Framework\DataAbstractionLayer\Util\StatementHelper` to simplify binding multiple parameters to a statement

___
# Upgrade Information

## ExceptionHandlerInterface signature changes

The parameter and return type of `Shopwell\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface::matchException` have been changed from `\Exception` to `\Throwable`. Return type can be kept as before, but the parameter type must be changed from
```php
class MyExceptionHandler implements ExceptionHandlerInterface
{
    public function matchException(\Exception $exception): ?\Exception
    {
        // ...
    }
}
```
to
```php
class MyExceptionHandler implements ExceptionHandlerInterface
{
    public function matchException(\Throwable $exception): ?\Throwable
    {
        // ...
    }
}
```
As changes to the interface are breaking, all implementations of `ExceptionHandlerInterface` have been updated.
<details>
 <summary>List of updated implementations</summary>

 * `Shopwell\Core\System\Language\LanguageExceptionHandler`
 * `Shopwell\Core\System\SalesChannel\SalesChannelExceptionHandler`
 * `Shopwell\Core\Content\Product\DataAbstractionLayer\ProductExceptionHandler`
 * `Shopwell\Core\Content\Product\SalesChannel\Sorting\ProductSortingExceptionHandler`
 * `Shopwell\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingExceptionHandler`
 * `Shopwell\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigExceptionHandler`
 * `Shopwell\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldExceptionHandler`
 * `Shopwell\Core\Content\ProductExport\DataAbstractionLayer\ProductExportExceptionHandler`
 * `Shopwell\Core\Content\Category\DataAbstractionLayer\CategoryNonExistentExceptionHandler`
 * `Shopwell\Core\Content\Newsletter\NewsletterExceptionHandler`
 * `Shopwell\Core\Framework\DataAbstractionLayer\TechnicalNameExceptionHandler`
 * `Shopwell\Core\Checkout\Customer\DataAbstractionLayer\CustomerWishlistProductExceptionHandler`
 * `Shopwell\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceExceptionHandler`
 * `Shopwell\Core\Checkout\Order\OrderExceptionHandler`
</details>
If you extended any of these implementations, you need to update your code as well.

## QueryBuilder changes
As `Shopwell\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder` extends `Doctrine\DBAL\Query\QueryBuilder`, changes to the parent class might affect your code.
You might check next sections for changes to the parent class:

 * [Removed methods and constants](https://github.com/doctrine/dbal/blob/4.2.x/UPGRADE.md#bc-break-removed-querybuilder-methods-and-contstants)
 * [Changes in API QueryBuilderAPI](https://github.com/doctrine/dbal/blob/4.2.x/UPGRADE.md#bc-break-changes-in-the-querybuilder-api)

## Statement changes
`Doctrine\DBAL\Statement` has a lot of bc breaks. Some of them include
 * [Removed bindParam() method](https://github.com/doctrine/dbal/blob/4.2.x/UPGRADE.md#bc-break-removed-wrapper--and-driver-level-statementbindparam-methods)
 * [Removed support for using null as a parameter type](https://github.com/doctrine/dbal/blob/4.2.x/UPGRADE.md#bc-break-removed-support-for-using-null-as-prepared-statement-parameter-type)
 * [Marked ::execute() method private](https://github.com/doctrine/dbal/blob/4.2.x/UPGRADE.md#bc-break-statementexecute-marked-private)

To simplify binding multiple parameters to a statement, we added `Shopwell\Core\Framework\DataAbstractionLayer\Util\StatementHelper`. You can use it like this:
```php

$statement = $connection->prepare('SELECT * FROM product WHERE column1 = :param1 AND column2 = :param2');

StatementHelper::bindParameters($statement, [
    'param1' => 'value1',
    'param2' => 'value2',
]);

// for select queries use executeQuery, for insert, update, delete queries use executeStatement
$result = $statement->executeQuery();
```
Also it's possible to bind parameters and execute the statement/query in one call using `StatementHelper::executeStatement` or `StatementHelper::executeQuery`.
If statement is not reused, you may use `Connection::executeQuery` or `Connection::executeStatement` directly.

## Transaction savepoints enabled by default
As [support for nested transactions](https://github.com/doctrine/dbal/blob/4.2.x/UPGRADE.md#bc-break-remove-support-for-transaction-nesting-without-savepoints) without savepoints has been removed, savepoints are now enabled by default.
Savepoints allow partial rollback within a transaction. This means that if you attempt a rollback within a nested transaction, only the operations inside the current nesting level are rolled back.  Previously, if nested transaction was rolled back, the outer transactions was also rolled back.

Nested transactions are not used in the platform, but this change might affect any custom code that are using them.
