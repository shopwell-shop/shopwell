<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Draft;

use Shopwell\Core\Framework\ApprovalFlow\Mutation\GraphMutationResult;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class ApprovalDraftEdit
{
    public function __construct(
        public int $revision,
        public GraphMutationResult $mutation,
    ) {
    }
}
