<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Context;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\SalesChannelRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Restores a sales-channel context for late fallback/error handling.
 *
 * Use this only when domain resolution has already populated the sales-channel request attributes,
 * but normal route-based context resolution did not run because no route matched.
 *
 * @internal
 */
#[Package('framework')]
class SalesChannelContextRequestRestorer
{
    public function __construct(private readonly SalesChannelContextServiceInterface $contextService)
    {
    }

    public function restore(Request $request): ?SalesChannelContext
    {
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if ($context instanceof SalesChannelContext) {
            return $context;
        }

        $salesChannelId = $request->attributes->getString(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        if ($salesChannelId === '') {
            return null;
        }

        $currencyId = $request->attributes->getString(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID) ?: null;
        $domainId = $request->attributes->getString(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID) ?: null;
        $languageId = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID) ?: null;

        $context = $this->contextService->get(new SalesChannelContextServiceParameters(
            $salesChannelId,
            Uuid::randomHex(),
            $languageId,
            $currencyId,
            $domainId,
        ));

        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);

        return $context;
    }
}
