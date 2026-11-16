<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval\Node;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
final class ApprovalNodeCompilerRegistry
{
    /**
     * @var array<string, array<int, ApprovalNodeCompiler>>
     */
    private array $compilers = [];

    /**
     * @param iterable<ApprovalNodeCompiler> $compilers
     */
    public function __construct(iterable $compilers)
    {
        foreach ($compilers as $compiler) {
            $type = $compiler->getNodeType();
            $version = $compiler->getNodeVersion();

            if (preg_match('/^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/D', $type) !== 1 || $version < 1) {
                throw WorkflowException::invalidDocument('/compilation/compiler', 'Compiler identifiers or version are invalid.');
            }

            if (isset($this->compilers[$type][$version])) {
                throw WorkflowExecutionException::duplicateRegistration('approval node compiler', $type . '@' . $version);
            }

            $this->compilers[$type][$version] = $compiler;
        }
    }

    public function find(string $type, int $version): ?ApprovalNodeCompiler
    {
        return $this->compilers[$type][$version] ?? null;
    }

    public function hasType(string $type): bool
    {
        return isset($this->compilers[$type]);
    }
}
