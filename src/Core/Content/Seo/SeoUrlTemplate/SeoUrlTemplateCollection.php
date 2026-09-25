<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Seo\SeoUrlTemplate;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SeoUrlTemplateEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class SeoUrlTemplateCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'seo_url_template_collection';
    }

    protected function getExpectedClass(): string
    {
        return SeoUrlTemplateEntity::class;
    }
}
