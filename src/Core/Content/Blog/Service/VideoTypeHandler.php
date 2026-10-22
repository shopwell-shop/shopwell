<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * Renders a video page: the player in the header, the article body beneath it as the description.
 *
 * The page is an entry and not just an embedded player, which is why it keeps the written body -
 * search engines rank a page with a transcript or a description far better than a bare embed, and a
 * visitor who can not play the video still gets the content.
 *
 * @final
 */
#[Package('discovery')]
class VideoTypeHandler implements BlogTypeHandlerInterface
{
    final public const DETAIL_TEMPLATE = '@Storefront/storefront/page/blog/detail-video.html.twig';

    final public const SCHEMA_TYPE = 'VideoObject';

    public function supports(string $type): bool
    {
        return $type === BlogEntity::TYPE_VIDEO;
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
