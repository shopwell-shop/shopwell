<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Read-only option sources constrain request values without coupling the form to a business module.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class WorkflowFormOptionsEvent extends Event
{
    /**
     * @var list<array{value: string, label: string}>|null
     */
    public ?array $options = null;

    public function __construct(public readonly string $source, public readonly Context $context)
    {
    }
}
