<?php declare(strict_types=1);

namespace Shopwell\Administration\Snippet;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<AppAdministrationSnippetEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class AppAdministrationSnippetCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'administration_snippet_collection';
    }

    protected function getExpectedClass(): string
    {
        return AppAdministrationSnippetEntity::class;
    }
}
