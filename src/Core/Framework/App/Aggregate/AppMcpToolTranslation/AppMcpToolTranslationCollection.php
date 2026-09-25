<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\AppMcpToolTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 *
 * @extends EntityCollection<AppMcpToolTranslationEntity>
 */
#[Package('framework')]
class AppMcpToolTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppMcpToolTranslationEntity::class;
    }
}
