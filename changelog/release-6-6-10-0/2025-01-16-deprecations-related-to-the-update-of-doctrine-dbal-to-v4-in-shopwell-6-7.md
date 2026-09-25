---
title: Deprecations related to the update to doctrine/dbal:4 in shopwell 6.7
issue: NEXT-39353
---
# Core
* Changed IteratorFactory to pass \Shopwell\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder instead of Doctrine\DBAL\Query\QueryBuilder into LastIdQuery and OffsetQuery

___
# Next Major Version Changes
## OffsetQuery & LastIdQuery signature changes
OffsetQuery && LastIdQuery now accept `Shopwell\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder` instead of `Doctrine\DBAL\Query\QueryBuilder`.
If you are creating those classes manually, you need to change creating code:
```php
$queryBuilder = $this->connection->createQueryBuilder();
$lastIdQuery = new LastIdQuery($queryBuilder);
$offsetQuery = new OffsetQuery($queryBuilder);
```
to
```php
$queryBuilder = new \Shopwell\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder($this->connection);
$lastIdQuery = new LastIdQuery($queryBuilder);
$offsetQuery = new OffsetQuery($queryBuilder);
```

## IterableQuery::getQuery signature changes
`Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery::getQuery` will return `Shopwell\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder` instead of `Doctrine\DBAL\Query\QueryBuilder`.
Implementations of IterableQuery should be updated to return the correct type.
