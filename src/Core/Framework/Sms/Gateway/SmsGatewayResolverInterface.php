<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Gateway;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsTemplateEntity;

/**
 * Decides which sms_gateway carries a message.
 *
 * The whole chain lives behind this interface, so an extension can replace the routing as a whole
 * instead of adding a step to it.
 *
 * @internal
 */
#[Package('framework')]
interface SmsGatewayResolverInterface
{
    /**
     * The name that stands for the null gateway, used when no configured gateway is usable. Messages
     * still travel through the pipeline and are recorded, they simply do not leave the platform.
     */
    public const GATEWAY_NULL = 'null';

    /**
     * The sms_gateway ids that may carry the message, the most preferred one first. The caller falls
     * back to the null gateway when the list is empty.
     *
     * @param string|null $salesChannelId the sales channel the credentials and the global settings are read for,
     *                                    null reads the installation wide configuration only. It is passed in
     *                                    explicitly because a Context carries a sales channel only when it was
     *                                    built from a store api request - a queue worker or a console command has
     *                                    to name the channel it sends for.
     * @param string|null $countryCode the ISO code of the recipient, null when the number is not known yet
     * @param SmsTemplateEntity|null $template the logical template of a template based message, gateways
     *                                         without an approved mapping for it are skipped
     *
     * @return list<string>
     */
    public function resolveGatewayIds(
        Context $context,
        ?string $salesChannelId = null,
        ?string $countryCode = null,
        ?SmsTemplateEntity $template = null,
    ): array;
}
