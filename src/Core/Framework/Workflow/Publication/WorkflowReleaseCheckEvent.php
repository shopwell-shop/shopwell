<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Publishers recheck live prerequisites before a business binds an immutable release.
 * Listeners reject unavailable dependencies by throwing a domain exception; they must not write.
 * The caller authorizes the business configuration before dispatching this event.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class WorkflowReleaseCheckEvent extends Event
{
    public function __construct(public readonly WorkflowReleaseEntity $release, public readonly Context $context)
    {
    }
}
