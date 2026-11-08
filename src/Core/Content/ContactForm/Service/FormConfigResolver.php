<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ContactForm\Service;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('discovery')]
readonly class FormConfigResolver
{
    public function __construct(private SystemConfigService $systemConfigService)
    {
    }

    /**
     * @return array{receivers: array<string, string>, message: string}
     */
    public function resolve(SalesChannelContext $context): array
    {
        $salesChannelId = $context->getSalesChannelId();

        return [
            'receivers' => [
                $this->systemConfigService->getString('core.basicInformation.email', $salesChannelId) => $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId),
            ],
            'message' => '',
        ];
    }
}
