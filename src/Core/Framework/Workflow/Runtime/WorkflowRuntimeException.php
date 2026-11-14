<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Runtime;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class WorkflowRuntimeException extends WorkflowException
{
    public static function unavailable(string $name): self
    {
        return new self(Response::HTTP_CONFLICT, 'FRAMEWORK__WORKFLOW_RUNTIME_UNAVAILABLE', 'Workflow runtime "{{ name }}" is unavailable.', ['name' => $name]);
    }

    public static function duplicate(string $name): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, 'FRAMEWORK__WORKFLOW_RUNTIME_DUPLICATE', 'Workflow runtime "{{ name }}" is registered more than once.', ['name' => $name]);
    }

    public static function invalidReference(): self
    {
        return new self(Response::HTTP_CONFLICT, 'FRAMEWORK__WORKFLOW_RUNTIME_INVALID_REFERENCE', 'The workflow instance does not belong to the bound release.');
    }

    public static function enclosingTransaction(): self
    {
        return new self(Response::HTTP_CONFLICT, 'FRAMEWORK__WORKFLOW_RUNTIME_ENCLOSING_TRANSACTION', 'Workflow runtime must own the outer transaction.');
    }
}
