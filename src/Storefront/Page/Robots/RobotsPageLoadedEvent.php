<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Robots;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class RobotsPageLoadedEvent extends NestedEvent implements ShopwellEvent
{
    public function __construct(
        private readonly RobotsPage $page,
        private readonly Context $context,
        private readonly Request $request,
    ) {
    }

    public function getPage(): RobotsPage
    {
        return $this->page;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
