<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Publication\ApprovalReleaseCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Publication\CompiledApprovalRelease;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseStore;
use Shopwell\Core\Framework\Log\Package;

/**
 * Revalidates persisted compilation content against this deployment, without activation authority.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoaderTest
 */
#[Package('framework')]
class ApprovalReleaseLoader
{
    public function __construct(
        private readonly ApprovalReleaseStore $releases,
        private readonly ApprovalReleaseCompiler $compiler,
    ) {
    }

    public function load(string $releaseId): CompiledApprovalRelease
    {
        $stored = $this->releases->readContent($releaseId);
        $compiled = $this->compiler->compile(new DraftGraph($stored['graphJson']));
        $parts = [
            'graphJson' => [$stored['graphJson'], $compiled->graphJson],
            'contractJson' => [$stored['contractJson'], $compiled->contractJson],
            'graphPlanJson' => [$stored['graphPlanJson'], $compiled->graphPlanJson],
            'nodePlanJson' => [$stored['nodePlanJson'], $compiled->nodePlanJson],
            'dependenciesJson' => [$stored['dependenciesJson'], $compiled->dependenciesJson],
            'contentHash' => [$stored['contentHash'], $compiled->hash],
        ];
        foreach ($parts as $part => [$expected, $actual]) {
            if ($expected !== $actual) {
                throw ApprovalFlowException::releaseVerificationFailed($releaseId, $part);
            }
        }

        return $compiled;
    }
}
