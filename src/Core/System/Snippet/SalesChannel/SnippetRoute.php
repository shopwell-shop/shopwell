<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\SalesChannel;

use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Adapter\Translation\Translator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:STORE_API_SNIPPETS
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class SnippetRoute extends AbstractSnippetRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelSnippetLoader $snippetLoader,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    #[Route(
        path: '/store-api/snippet',
        name: 'store-api.snippet',
        methods: [Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(Request $request, SalesChannelContext $context): SnippetRouteResponse
    {
        $results = $this->snippetLoader->load(
            $this->parseList($request->query->getString('languageIds')),
            $this->parseList($request->query->getString('prefixes')),
            $context
        );

        foreach ($results as $result) {
            $this->cacheTagCollector->addTag(Translator::tag($result->snippetSetId));
        }

        $response = new SnippetRouteResponse(new SnippetSetResultList(...$results));

        $hashes = array_map(static fn (SnippetSetResult $result): string => $result->hash, $results);
        // a single set keeps its own hash as etag, multiple sets are combined into one
        $etag = \count($hashes) === 1 ? implode('', $hashes) : Hasher::hash(implode('-', $hashes));

        $response->setEtag($etag);
        $response->isNotModified($request);

        return $response;
    }

    public function getDecorated(): AbstractSnippetRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return list<string>
     */
    private function parseList(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $item): bool => $item !== ''
        ));
    }
}
