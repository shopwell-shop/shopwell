<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\AppMcpPromptTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 *
 * @extends EntityCollection<AppMcpPromptTranslationEntity>
 */
#[Package('framework')]
class AppMcpPromptTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppMcpPromptTranslationEntity::class;
    }
}
