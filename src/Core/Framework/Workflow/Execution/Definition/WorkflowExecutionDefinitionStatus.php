<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Definition;

use Shopwell\Core\Framework\Log\Package;

/**
 * Definition statuses use protected writes rather than state-machine history for frequent draft saves.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowExecutionDefinitionStatus
{
    /**
     * Editable draft; cannot be started.
     */
    public const DRAFT = 'draft';

    /**
     * Published definition available for starting instances.
     */
    public const PUBLISHED = 'published';

    /**
     * Disabled for new instances; existing instances continue.
     */
    public const DISABLED = 'disabled';

    public const ALL = [self::DRAFT, self::PUBLISHED, self::DISABLED];
}
