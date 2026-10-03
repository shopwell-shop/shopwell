<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\Extension;

use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\Snippet\SalesChannel\SnippetRouteResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @experimental stableVersion:v6.8.0 feature:STORE_API_SNIPPETS
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<SnippetRouteResponse>
 */
#[Package('discovery')]
final class SnippetRouteExtension extends Extension
{
    public const NAME = 'snippet-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
