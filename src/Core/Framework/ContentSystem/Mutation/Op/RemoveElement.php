<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Mutation\Op;

use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopwell\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopwell\Core\Framework\Log\Package;

/**
 * Deletes $elementId and its whole subtree. Surviving elements' wiring is left untouched, and no
 * surviving element is affected.
 *
 * @internal
 */
#[Package('framework')]
final class RemoveElement extends AbstractLayoutMutation
{
    public function __construct(
        private readonly string $elementId,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        if ($tree->find($this->elementId) === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        return $tree->remove($this->elementId);
    }
}
