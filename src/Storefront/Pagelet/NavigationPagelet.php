<?php declare(strict_types=1);

namespace Shopwell\Storefront\Pagelet;

use Shopwell\Core\Content\Category\Tree\Tree;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
abstract class NavigationPagelet extends Pagelet
{
    public function __construct(
        protected ?Tree $navigation,
    ) {
    }

    public function getNavigation(): ?Tree
    {
        return $this->navigation;
    }
}
