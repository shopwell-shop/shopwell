<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Stub\ContentSystem;

use Shopwell\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopwell\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopwell\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopwell\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopwell\Core\Framework\Log\Package;

/**
 * Shared mapping admission wiring; tests supply their own type and candidate registries.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class MappingTestFactory
{
    public static function inspector(AbstractContentSystemElementTypeRegistry $types, AbstractContentSystemMappingCandidateRegistry $candidates, InlineMappingTokenParser $parser = new InlineMappingTokenParser()): StoredMappingInspector
    {
        return new StoredMappingInspector($types, $candidates, new MappingTypeCompatibility(), new MappingConsumers(), $parser);
    }
}
