<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Declare plugin operations before publication and task policy evaluation.
 * Custom names must be namespaced; declarations do not enable operations on a node.
 */
#[Package('framework')]
final class ApprovalOperationRegistrationEvent extends Event
{
    /**
     * @var list<string>
     */
    private array $names = ApprovalTaskActions::OPERATIONS;

    public function add(string $name): void
    {
        if (!preg_match('/^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/D', $name) || \in_array($name, $this->names, true)) {
            throw WorkflowException::invalidDocument('/operations', 'Operation names must be unique namespaced identifiers.');
        }

        $this->names[] = $name;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return $this->names;
    }
}
