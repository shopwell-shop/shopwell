<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Blog;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the blog listing page is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.7.18.0
 *
 * @final
 */
#[Package('discovery')]
class BlogPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'blog-page-loaded';

    public function __construct(
        private readonly BlogPage $page,
        SalesChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): BlogPage
    {
        return $this->page;
    }
}
