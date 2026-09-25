---
title: Deprecate CreateSchemaCommand and SchemaGenerator 
issue: NEXT-33257
author: Marcus Müller
author_email: 25648755+M-arcus@users.noreply.github.com
author_github: @M-arcus
---
# Core
* Deprecated `\Shopwell\Core\Framework\DataAbstractionLayer\Command\CreateSchemaCommand` and `\Shopwell\Core\Framework\DataAbstractionLayer\SchemaGenerator`, use `\Shopwell\Core\Framework\DataAbstractionLayer\Command\CreateMigrationCommand` and `\Shopwell\Core\Framework\DataAbstractionLayer\MigrationQueryGenerator` instead
___
# Next Major Version Changes

## \Shopwell\Core\Framework\DataAbstractionLayer\Command\CreateSchemaCommand:
`\Shopwell\Core\Framework\DataAbstractionLayer\Command\CreateSchemaCommand` will be removed. You can use `\Shopwell\Core\Framework\DataAbstractionLayer\Command\CreateMigrationCommand` instead.

## \Shopwell\Core\Framework\DataAbstractionLayer\SchemaGenerator:
`\Shopwell\Core\Framework\DataAbstractionLayer\SchemaGenerator` will be removed. You can use `\Shopwell\Core\Framework\DataAbstractionLayer\MigrationQueryGenerator` instead.
