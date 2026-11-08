<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Mutation\Op;

use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerBaseKeyResolver;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopwell\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopwell\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopwell\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopwell\Core\Framework\Log\Package;

/**
 * Maps one declared property to a catalogued path on the layout's root source.
 *
 * @internal
 */
#[Package('framework')]
final class MapProperty extends AbstractLayoutMutation
{
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $typeRegistry,
        private readonly StoredMappingInspector $mappingInspector,
        private readonly string $elementId,
        private readonly string $propertyKey,
        private readonly MappingSourceReference $source,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        if ($tree->rootSource === null || $tree->rootSource === '') {
            throw ContentSystemException::mappingRootSourceMissing($this->elementId, $this->propertyKey);
        }

        $node = $tree->find($this->elementId);

        if ($node === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        $this->requireRegistered($this->typeRegistry, $node->component);
        $candidate = $this->mappingInspector->admitProperty($node->component, $this->propertyKey, $this->source, $tree->rootSource);
        if ($candidate instanceof ContentSystemException) {
            throw $candidate;
        }

        $definitions = $node->contextDefinitions;
        $consumers = $definitions->getAllConsumers();
        $baseKeyResolver = new ConsumerBaseKeyResolver();
        $mappingConsumers = new MappingConsumers();
        $targetBaseKey = $baseKeyResolver->resolve($this->propertyKey);

        foreach ($consumers as $contextKey => $consumer) {
            $contextKey = (string) $contextKey;
            $baseKey = $baseKeyResolver->resolve($consumer->propertyAlias ?? $contextKey);
            if ($contextKey !== $this->propertyKey && $baseKey !== $targetBaseKey) {
                continue;
            }

            if ($contextKey !== $this->propertyKey || !$mappingConsumers->isMapping($consumer)) {
                throw ContentSystemException::propertyAliasCollision($targetBaseKey, $contextKey, $this->propertyKey);
            }
        }

        $consumers[$this->propertyKey] = new ContextConsumer(
            type: $candidate->contextType,
            required: false,
            scope: ConsumerScope::Root,
            source: $candidate->source,
        );

        $replacement = $node->withContextDefinitions(new ContextDefinitions(
            $definitions->getAllProviders(),
            $consumers,
        ));
        $this->affected = [$replacement->id];

        return $tree->replace($this->elementId, $replacement);
    }
}
