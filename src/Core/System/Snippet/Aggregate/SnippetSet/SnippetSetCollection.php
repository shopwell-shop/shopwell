<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\Aggregate\SnippetSet;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SnippetSetEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SnippetSetCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'snippet_set_collection';
    }

    protected function getExpectedClass(): string
    {
        return SnippetSetEntity::class;
    }
}
