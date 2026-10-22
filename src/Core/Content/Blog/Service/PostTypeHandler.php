<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * Renders an article: the written entry with teaser, body and byline.
 *
 * @final
 */
#[Package('discovery')]
class PostTypeHandler implements BlogTypeHandlerInterface
{
    final public const DETAIL_TEMPLATE = '@Storefront/storefront/page/blog/detail.html.twig';

    final public const SCHEMA_TYPE = 'BlogPosting';

    public function supports(string $type): bool
    {
        return $type === BlogEntity::TYPE_POST;
    }

    public function getDetailTemplate(): string
    {
        return self::DETAIL_TEMPLATE;
    }

    public function getSchemaType(): string
    {
        return self::SCHEMA_TYPE;
    }
}
