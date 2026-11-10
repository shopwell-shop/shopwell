<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class BlogContentLayoutDefinition extends AbstractContentLayoutAssignableDefinition
{
    final public const ENTITY_NAME = 'blog_content_layout';

    final public const CONTENT_LAYOUT_ENTITY_TYPE = 'blog';

    final public const CONFIG_KEY_DEFAULT_CONTENT_LAYOUT = 'core.content_system.default_blog_content_layout';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return BlogContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BlogContentLayoutCollection::class;
    }

    public function getContentLayoutEntityType(): string
    {
        return self::CONTENT_LAYOUT_ENTITY_TYPE;
    }

    public function getCacheTags(string $entityId): array
    {
        return ['blog-' . $entityId];
    }

    public function getContentLayoutDefaultConfigKey(): string
    {
        return self::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT;
    }

    public function getPageDataRequirements(): array
    {
        return [
            ...parent::getPageDataRequirements(),
            new DataRequirement('blogDetail', BlogDetailDataLoader::SOURCE, new BlogDetailLoaderConfig('blogId')),
        ];
    }

    protected function getEntityAssociations(): array
    {
        return ['author', 'categories.blogCategory', 'videos', 'tags'];
    }

    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();
        $fields->add((new FkField('blog_id', 'blogId', 'blog'))->addFlags(new Required(), new ApiAware(AdminApiSource::class)));

        return $fields;
    }

    protected function defineEntityIdField(): IdField
    {
        return new IdField('blog_id', 'blogId');
    }
}
