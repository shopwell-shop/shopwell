<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * @internal
 *
 * 按 `assigneeType` 找解析器。
 *
 * `tagged_iterator` 装配，新增一种审批人只需加一个类，不改这里。
 */
#[Package('framework')]
final class AssigneeResolverRegistry
{
    /**
     * @var array<string, AssigneeResolverInterface>|null
     */
    private ?array $resolved = null;

    /**
     * @param iterable<AssigneeResolverInterface> $resolvers
     */
    public function __construct(private readonly iterable $resolvers)
    {
    }

    public function for(string $assigneeType): AssigneeResolverInterface
    {
        return $this->map()[$assigneeType] ?? throw WorkflowExecutionException::unsupportedAssigneeType($assigneeType);
    }

    public function has(string $assigneeType): bool
    {
        return isset($this->map()[$assigneeType]);
    }

    /**
     * @return array<string, AssigneeResolverInterface>
     */
    private function map(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $map = [];

        foreach ($this->resolvers as $resolver) {
            $type = $resolver->getAssigneeType();

            if (isset($map[$type])) {
                throw WorkflowExecutionException::duplicateRegistration('runtime provider', $type);
            }

            $map[$type] = $resolver;
        }

        return $this->resolved = $map;
    }
}
