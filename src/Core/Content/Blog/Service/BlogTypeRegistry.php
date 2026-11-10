<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * Registered authoring types and their storefront presentation defaults.
 *
 * Extensions register additional types with addType(), following Product's registry pattern.
 *
 * @final
 */
#[Package('discovery')]
class BlogTypeRegistry
{
    final public const DEFAULT_TEMPLATE = '@Storefront/storefront/page/blog/detail.html.twig';

    /**
     * @var list<string>
     */
    private array $types = BlogEntity::TYPE_LIST;

    /**
     * @var array<string, array{template: string, schemaType: string}>
     */
    private array $presentation = [
        BlogEntity::TYPE_POST => ['template' => self::DEFAULT_TEMPLATE, 'schemaType' => 'BlogPosting'],
        BlogEntity::TYPE_VIDEO => ['template' => '@Storefront/storefront/page/blog/detail-video.html.twig', 'schemaType' => 'VideoObject'],
    ];

    public function addType(string $type, string $template = self::DEFAULT_TEMPLATE, string $schemaType = 'BlogPosting'): void
    {
        if ($this->hasType($type)) {
            return;
        }

        $this->types[] = $type;
        $this->presentation[$type] = ['template' => $template, 'schemaType' => $schemaType];
    }

    /**
     * @return list<string>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function hasType(string $type): bool
    {
        return \in_array($type, $this->types, true);
    }

    public function getDetailTemplate(string $type): string
    {
        return $this->presentation[$type]['template'] ?? self::DEFAULT_TEMPLATE;
    }

    public function getSchemaType(string $type): string
    {
        return $this->presentation[$type]['schemaType'] ?? 'BlogPosting';
    }
}
