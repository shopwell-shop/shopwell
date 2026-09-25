<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms\Aggregate\CmsSection;

use Shopwell\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CmsSectionEntity>
 */
#[Package('discovery')]
class CmsSectionCollection extends EntityCollection
{
    public function getBlocks(): CmsBlockCollection
    {
        $blocks = new CmsBlockCollection();

        /** @var CmsSectionEntity $section */
        foreach ($this->elements as $section) {
            if (!$section->getBlocks()) {
                continue;
            }

            $blocks->merge($section->getBlocks());
        }

        return $blocks;
    }

    public function getApiAlias(): string
    {
        return 'cms_page_section_collection';
    }

    /**
     * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
     */
    public function hasBlockWithType(string $type): bool
    {
        return $this->firstWhere(static fn (CmsSectionEntity $section) => $section->getBlocks()?->hasBlockWithType($type) === true) !== null;
    }

    protected function getExpectedClass(): string
    {
        return CmsSectionEntity::class;
    }
}
