<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Mutation\Op;

use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopwell\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopwell\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopwell\Core\Framework\Log\Package;

/**
 * Removes one property mapping without touching its authored fallback value.
 *
 * @internal
 */
#[Package('framework')]
final class UnmapProperty extends AbstractLayoutMutation
{
    public function __construct(
        private readonly string $elementId,
        private readonly string $propertyKey,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        $node = $tree->find($this->elementId);

        if ($node === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        $definitions = $node->contextDefinitions;
        $consumers = $definitions->getAllConsumers();
        $consumer = $consumers[$this->propertyKey] ?? null;

        if ($consumer === null || !(new MappingConsumers())->isMapping($consumer)) {
            return $tree;
        }

        unset($consumers[$this->propertyKey]);

        $replacement = $node->withContextDefinitions(new ContextDefinitions(
            $definitions->getAllProviders(),
            $consumers,
        ));
        $this->affected = [$replacement->id];

        return $tree->replace($this->elementId, $replacement);
    }
}
