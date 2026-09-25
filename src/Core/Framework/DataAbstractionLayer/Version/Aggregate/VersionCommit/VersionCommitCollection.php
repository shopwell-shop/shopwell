<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<VersionCommitEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class VersionCommitCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getUserIds(): array
    {
        return $this->fmap(static fn (VersionCommitEntity $versionChange) => $versionChange->getUserId());
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(static fn (VersionCommitEntity $versionChange) => $versionChange->getUserId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'dal_version_commit_collection';
    }

    protected function getExpectedClass(): string
    {
        return VersionCommitEntity::class;
    }
}
