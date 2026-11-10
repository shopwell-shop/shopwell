<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Routing;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingEntity;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class SmsGatewayResolution
{
    /**
     * @param list<string> $gatewayIds
     * @param array<string, SmsTemplateMappingEntity|null> $mappings
     */
    public function __construct(
        public array $gatewayIds,
        public array $mappings,
        public ?SmsException $refusal = null,
    ) {
    }
}
