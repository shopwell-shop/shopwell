<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MediaEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class MediaCollection extends EntityCollection
{
    /**
     * @return array<array-key, string>
     */
    public function getUserIds(): array
    {
        return $this->fmap(static fn (MediaEntity $media) => $media->getUserId());
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(static fn (MediaEntity $media) => $media->getUserId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'media_collection';
    }

    protected function getExpectedClass(): string
    {
        return MediaEntity::class;
    }
}
