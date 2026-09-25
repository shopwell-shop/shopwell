<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SnippetEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SnippetCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'snippet_collection';
    }

    protected function getExpectedClass(): string
    {
        return SnippetEntity::class;
    }
}
