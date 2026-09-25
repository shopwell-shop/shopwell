<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\DataTransfer\SnippetPath;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<SnippetPath>
 */
#[Package('discovery')]
class SnippetPathCollection extends Collection
{
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function add($element): void
    {
        $this->set($element->location, $element);
    }

    public function hasPath(SnippetPath $snippetPath): bool
    {
        return $this->has($snippetPath->location);
    }

    /**
     * @return list<string>
     */
    public function toLocationArray(): array
    {
        return \array_values($this->map(static fn (SnippetPath $snippetPath) => $snippetPath->location));
    }

    protected function getExpectedClass(): ?string
    {
        return SnippetPath::class;
    }
}
