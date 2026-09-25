<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\AppMcpPrompt;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 *
 * @extends EntityCollection<AppMcpPromptEntity>
 */
#[Package('framework')]
class AppMcpPromptCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppMcpPromptEntity::class;
    }
}
