<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Twig;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends \IteratorAggregate<int, string>
 */
#[Package('framework')]
interface TemplatePathIteratorInterface extends \IteratorAggregate
{
    /**
     * @return iterable<string>
     */
    public function getTemplatePathsForSubPath(string $subPath, bool $includeDotFiles = false): iterable;
}
