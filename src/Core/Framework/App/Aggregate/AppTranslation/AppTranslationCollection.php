<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\AppTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<AppTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppTranslationEntity::class;
    }
}
