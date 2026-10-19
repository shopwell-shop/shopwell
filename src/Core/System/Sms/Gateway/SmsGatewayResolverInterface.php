<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;

/**
 * Decides which sms_gateway carries a message.
 *
 * The whole chain lives behind this interface, so an extension can replace the routing as a whole
 * instead of adding a step to it.
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

    /**
     * Why no gateway was picked for the same arguments, null when one was - so a delivery record can
     * name the reason instead of claiming that nothing is configured.
     *
     * Only the routing knows which gate it was that dropped every candidate, so only the routing can
     * answer this. A replacement of the routing has to answer it as well: a record that guesses is
     * worse than no record.
     *
     * @param string|null $salesChannelId the channel the resolution is made for, see resolveGatewayIds()
     * @param string|null $countryCode the ISO code of the recipient, null when it is not known
     * @param SmsTemplateEntity|null $template the logical template of a template based message
     *
     * @return SmsException|null null when a gateway was picked, when the arguments were never able to
     *                           yield one - a switched off channel, no configured gateway - or when the
     *                           template was simply never reported to a provider
     */
    public function undeliverableReason(
        Context $context,
        ?string $salesChannelId = null,
        ?string $countryCode = null,
        ?SmsTemplateEntity $template = null,
    ): ?SmsException;
}
