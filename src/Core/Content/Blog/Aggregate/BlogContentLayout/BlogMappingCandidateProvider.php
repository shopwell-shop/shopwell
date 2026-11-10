<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout;

use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopwell\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopwell\Core\Framework\Log\Package;

/**
 * The mapping catalogue for a blog layout.
 *
 * @internal
 */
#[Package('discovery')]
class BlogMappingCandidateProvider extends AbstractMappingCandidateProvider
{
    private const SNIPPET_ROOT = 'sw-experience-studio.mapping.blog';

    public function __construct(private readonly BlogContentLayoutDefinition $definition)
    {
    }

    public static function registeredCandidateKeys(): array
    {
        return ['blog' => [
            'blog.title',
            'blog.teaser',
            'blog.content',
            'blog.type',
            'blog.metaTitle',
            'blog.metaDescription',
            'blog.keywords',
        ]];
    }

    public function supports(string $rootSource): bool
    {
        return $this->definition->getContentLayoutEntityType() === $rootSource;
    }

    public function provide(string $rootSource): array
    {
        return [
            $this->text('title', 'basic'),
            $this->text('teaser', 'basic'),
            $this->text('content', 'basic'),
            $this->text('type', 'basic'),
            $this->text('metaTitle', 'seo'),
            $this->text('metaDescription', 'seo'),
            $this->text('keywords', 'seo'),
        ];
    }

    public function resolveRootSource(MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        $blog = $context->rootValues[$source->id] ?? null;
        if (!$blog instanceof BlogEntity) {
            return MappingSourceResolution::noValue();
        }

        $value = match ($source->path) {
            'title' => $blog->title,
            'teaser' => $blog->teaser,
            'content' => $blog->content,
            'type' => $blog->type,
            'metaTitle' => $blog->metaTitle,
            'metaDescription' => $blog->metaDescription,
            'keywords' => $blog->keywords,
            default => null,
        };

        return \in_array($source->path, ['title', 'teaser', 'content', 'type', 'metaTitle', 'metaDescription', 'keywords'], true)
            ? MappingSourceResolution::resolved($value)
            : MappingSourceResolution::noValue();
    }

    private function text(string $member, string $group): MappingCandidate
    {
        return new MappingCandidate(
            path: $this->definition->getContentLayoutEntityType() . '.' . $member,
            label: self::SNIPPET_ROOT . '.' . $member . '.label',
            description: self::SNIPPET_ROOT . '.' . $member . '.description',
            group: $group,
            valueType: 'string',
        );
    }
}
