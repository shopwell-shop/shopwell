<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Extension;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;

/**
 * Reorder or remove eligible gateways; newly introduced IDs are discarded by the resolver.
 *
 * @extends Extension<list<string>>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class SelectSmsGatewayExtension extends Extension
{
    public const NAME = 'sms.gateway.select';

    /**
     * @param list<string> $gatewayIds
     *
     * @internal
     */
    public function __construct(
        public array $gatewayIds,
        public readonly Context $context,
        public readonly ?string $salesChannelId,
        public readonly ?string $countryCode,
        public readonly ?SmsTemplateEntity $template,
    ) {
    }
}
