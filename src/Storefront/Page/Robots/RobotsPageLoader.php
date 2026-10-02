<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Robots;

use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Storefront\Page\Robots\Parser\RobotsDirectiveParser;
use Shopwell\Storefront\Page\Robots\Struct\DomainRuleCollection;
use Shopwell\Storefront\Page\Robots\Struct\DomainRuleStruct;
use Shopwell\Storefront\Page\Robots\Struct\RobotsUserAgentBlock;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
class RobotsPageLoader
{
    /**
     * @internal
     *
     * @param EntityRepository<SalesChannelDomainCollection> $salesChannelDomainRepository
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityRepository $salesChannelDomainRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly RobotsDirectiveParser $parser
    ) {
    }

    public function load(Request $request, Context $context): RobotsPage
    {
        $page = new RobotsPage();

        $hostname = $request->server->get('HTTP_HOST');

        if (\is_string($hostname) && $hostname !== '') {
            $domains = $this->getDomains($hostname, $context);

            [$globalBlocks, $domainRules] = $this->collectRules($hostname, $domains, $context);

            $page->setGlobalUserAgentBlocks($globalBlocks);
            $page->setDomainRules($domainRules);
            $page->setSitemaps($this->getSitemaps($domains, $hostname));
        } else {
            $page->setGlobalUserAgentBlocks([]);
            $page->setDomainRules(new DomainRuleCollection());
            $page->setSitemaps([]);
        }

        $this->eventDispatcher->dispatch(
            new RobotsPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    /**
     * @param non-empty-string $hostname
     */
    private function getDomains(string $hostname, Context $context): SalesChannelDomainCollection
    {
        $criteria = new Criteria();
        $criteria
            // Filter on the bare host: domains on a default port carry no port in their URL
            ->addFilter(new ContainsFilter('url', parse_url('//' . $hostname, \PHP_URL_HOST) ?: $hostname))
            ->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT))
        ;

        return $this->salesChannelDomainRepository->search($criteria, $context)->getEntities();
    }

    /**
     * Collects and separates global User-agent blocks from domain-specific path rules.
     *
     * @param non-empty-string $hostname
     *
     * @return array{0: list<RobotsUserAgentBlock>, 1: DomainRuleCollection}
     */
    private function collectRules(string $hostname, SalesChannelDomainCollection $domains, Context $context): array
    {
        $domainRuleCollection = new DomainRuleCollection();
        $globalBlocks = [];
        $globalBlocksByHash = [];

        $selectedDomains = $this->selectDomainsByHostname($domains, $hostname);

        foreach ($selectedDomains as $domainHostname => $domain) {
            $domainRules = trim($this->systemConfigService->getString('core.basicInformation.robotsRules', $domain->getSalesChannelId()));

            if ($domainRules === '') {
                continue;
            }

            // Parse the configuration
            $parsed = $this->parser->parse($domainRules, $context, $domain->getSalesChannelId());

            // Collect global User-agent blocks (deduplicate by hash)
            foreach ($parsed->userAgentBlocks as $block) {
                $hash = $block->getHash();
                if (!isset($globalBlocksByHash[$hash])) {
                    $globalBlocksByHash[$hash] = [
                        'block' => $block,
                        'pathDirectives' => [],
                    ];
                }

                // Collect path directives from this block for this domain
                foreach ($block->getPathDirectives() as $directive) {
                    $directiveWithPath = $directive->withBasePath($domainHostname);
                    $globalBlocksByHash[$hash]['pathDirectives'][] = $directiveWithPath;
                }
            }

            // Create domain rule struct with parsed data
            $domainRuleCollection->add(new DomainRuleStruct($parsed, $domainHostname));
        }

        // Build final global blocks with merged path directives
        foreach ($globalBlocksByHash as $data) {
            $block = $data['block'];
            $pathDirectives = $data['pathDirectives'];

            // Merge non-path directives with collected path directives
            $allDirectives = array_merge($block->getNonPathDirectives(), $pathDirectives);

            $globalBlocks[] = new RobotsUserAgentBlock($block->userAgent, $allDirectives);
        }

        return [$globalBlocks, $domainRuleCollection];
    }

    /**
     * @param non-empty-string $hostname
     *
     * @return list<string>
     */
    private function getSitemaps(SalesChannelDomainCollection $domains, string $hostname): array
    {
        $sitemaps = [];
        $selectedDomains = $this->selectDomainsByHostname($domains, $hostname);

        // Generate sitemaps from the selected domains
        foreach ($selectedDomains as $domain) {
            $sitemaps[] = $domain->getUrl() . '/sitemap.xml';
        }

        return $sitemaps;
    }

    /**
     * Selects domains matching the given hostname and port exactly, preferring HTTPS over HTTP
     * when the same host has both. Falls back to subdomains of the hostname (e.g.
     * `www.example.com` for `example.com`) when no domain matches exactly.
     *
     * @param non-empty-string $hostname
     *
     * @return array<string, SalesChannelDomainEntity> Array keyed by the domain's URL path
     *                                                 (e.g. `/en`, `''` for the root) with
     *                                                 selected domain entities
     */
    private function selectDomainsByHostname(SalesChannelDomainCollection $domains, string $hostname): array
    {
        \assert($hostname !== '');

        // HTTP_HOST is a bare `host[:port]` for HTTP and HTTPS alike. The `//` prefix only lets
        // parse_url() split it into host and port
        $requestParts = parse_url('//' . $hostname) ?: [];
        $requestHost = strtolower($requestParts['host'] ?? $hostname);
        $requestPort = $requestParts['port'] ?? null;

        $exactMatches = [];
        $subdomainMatches = [];

        foreach ($domains as $domain) {
            $domainParts = parse_url($domain->getUrl()) ?: [];
            $domainHost = strtolower($domainParts['host'] ?? '');
            $domainPort = $domainParts['port'] ?? (($domainParts['scheme'] ?? 'http') === 'https' ? 443 : 80);

            // A header without a port stays lenient: proxies often strip it while the domain keeps it
            if ($requestPort !== null && $requestPort !== $domainPort) {
                continue;
            }

            if ($domainHost === $requestHost) {
                $exactMatches[] = $domain;
            } elseif (str_ends_with($domainHost, '.' . $requestHost)) {
                $subdomainMatches[] = $domain;
            }
        }

        // `getDomains()` uses a substring filter, so hosts like `www.example.com` or `myexample.com`
        // show up for `example.com` as well. Only subdomains are used, and only when nothing matches
        // exactly: crawlers always fetch robots.txt on the bare host (see FriendsOfShopwell/FroshRobotsTxt#3).
        $selectedDomains = [];

        foreach ($exactMatches ?: $subdomainMatches as $domain) {
            $domainUrl = $domain->getUrl();
            $domainPath = (string) (parse_url($domainUrl, \PHP_URL_PATH) ?? '');

            $existingDomain = $selectedDomains[$domainPath] ?? null;
            $isHttps = str_starts_with($domainUrl, 'https://');

            if ($existingDomain === null) {
                $selectedDomains[$domainPath] = $domain;
            } elseif ($isHttps && !str_starts_with($existingDomain->getUrl(), 'https://')) {
                $selectedDomains[$domainPath] = $domain;
            }
        }

        return $selectedDomains;
    }
}
