<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\Files;

use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
interface SnippetFileLoaderInterface
{
    public function loadSnippetFilesIntoCollection(SnippetFileCollection $snippetFileCollection): void;
}
