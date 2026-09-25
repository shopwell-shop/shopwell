<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\SystemCheck;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\SystemCheck\BaseCheck;
use Shopwell\Core\Framework\SystemCheck\Check\Category;
use Shopwell\Core\Framework\SystemCheck\Check\Result;
use Shopwell\Core\Framework\SystemCheck\Check\Status;
use Shopwell\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopwell\Storefront\Framework\SystemCheck\Util\AbstractSalesChannelDomainProvider;
use Shopwell\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Storefront\Framework\HealthCheck\SalesChannelsReadinessCheckTest
 */
#[Package('discovery')]
class SalesChannelsReadinessCheck extends BaseCheck
{
    private const INDEX_PAGE = 'frontend.home.page';

    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelDomainUtil $util,
        private readonly AbstractSalesChannelDomainProvider $domainProvider,
    ) {
    }

    public function run(): Result
    {
        return $this->util->runAsSalesChannelRequest(
            fn () => $this->util->runWhileTrustingAllHosts(
                fn () => $this->doRun()
            )
        );
    }

    public function category(): Category
    {
        return Category::FEATURE;
    }

    public function name(): string
    {
        return 'SalesChannelsReadiness';
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return SystemCheckExecutionContext::readiness();
    }

    private function doRun(): Result
    {
        $domains = $this->domainProvider->fetchSalesChannelDomains();

        $extra = [];
        $requestStatus = [];
        foreach ($domains as $domain) {
            $url = $this->util->generateDomainUrl($domain->url, self::INDEX_PAGE);

            $request = Request::create($url);
            $result = $this->util->handleRequest($request);

            $status = $result->responseCode >= Response::HTTP_BAD_REQUEST ? Status::FAILURE : Status::OK;
            $requestStatus[$status->name] = $status;

            $extra[] = $result->getVars();
        }

        $finalStatus = \count($requestStatus) === 1 ? current($requestStatus) : Status::ERROR;

        return new Result(
            $this->name(),
            $finalStatus,
            $finalStatus === Status::OK ? 'All sales channels are OK' : 'Some or all sales channels are unhealthy.',
            $finalStatus === Status::OK,
            $extra
        );
    }
}
