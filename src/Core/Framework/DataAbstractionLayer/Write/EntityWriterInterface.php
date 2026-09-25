<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Write;

use Shopwell\Core\Framework\Api\Sync\SyncOperation;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal use entity repository to write data
 */
#[Package('framework')]
interface EntityWriterInterface
{
    /**
     * @param list<SyncOperation> $operations
     */
    public function sync(array $operations, WriteContext $context): WriteResult;

    /**
     * @param array<array<string, mixed>> $rawData
     *
     * @return array<string, list<EntityWriteResult>>
     */
    public function upsert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array;

    /**
     * @param array<array<string, mixed>> $rawData
     *
     * @return array<string, list<EntityWriteResult>>
     */
    public function insert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array;

    /**
     * @param array<array<string, mixed>> $rawData
     *
     * @return array<string, list<EntityWriteResult>>
     */
    public function update(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array;

    /**
     * @param array<array<string, string>> $rawData
     */
    public function delete(EntityDefinition $definition, array $rawData, WriteContext $writeContext): WriteResult;
}
