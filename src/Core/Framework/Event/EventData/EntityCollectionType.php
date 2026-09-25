<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Event\EventData;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class EntityCollectionType implements EventDataType
{
    final public const TYPE = 'collection';

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    public function __construct(private readonly string $definitionClass)
    {
    }

    /**
     * @return array{type: string, entityClass: class-string<EntityDefinition>}
     */
    public function toArray(): array
    {
        return [
            'type' => self::TYPE,
            'entityClass' => $this->definitionClass,
        ];
    }
}
