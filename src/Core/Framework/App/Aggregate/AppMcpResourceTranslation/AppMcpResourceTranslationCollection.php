<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\AppMcpResourceTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 *
 * @extends EntityCollection<AppMcpResourceTranslationEntity>
 */
#[Package('framework')]
class AppMcpResourceTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppMcpResourceTranslationEntity::class;
    }
}
