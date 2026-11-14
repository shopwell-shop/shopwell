<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Business modules can restrict a request action to its enabled release.
 * A null release hides the action until the business configuration is available.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class WorkflowRequestCatalogueEvent extends Event
{
    /**
     * @var array<string, string|null>
     */
    public array $restrictedActions = [];

    public function __construct(public readonly Context $context)
    {
    }
}
