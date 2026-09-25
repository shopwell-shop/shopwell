<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\Log\Package;

/**
 * @template IDStructure of string|array<string, string> = string
 *
 * @extends EntityWrittenEvent<IDStructure>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class EntityDeletedEvent extends EntityWrittenEvent
{
    /**
     * @param list<EntityWriteResult<IDStructure>> $writeResult
     * @param array<mixed> $errors
     */
    public function __construct(
        string $entityName,
        array $writeResult,
        Context $context,
        array $errors = []
    ) {
        parent::__construct($entityName, $writeResult, $context, $errors);

        $this->name = $entityName . '.deleted';
    }
}
