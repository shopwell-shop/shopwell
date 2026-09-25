<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\CmsBlockTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<AppCmsBlockTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class AppCmsBlockTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppCmsBlockTranslationEntity::class;
    }
}
