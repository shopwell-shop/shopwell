<?php declare(strict_types=1);

namespace Shopwell\Administration\Snippet;

use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
interface SnippetFinderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function findSnippets(string $locale): array;
}
