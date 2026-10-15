<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Struct;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * The options of a message a provider sends as a template reported at its side.
 *
 * The pipeline builds SmsMessageOptions out of the template and its mapping, a caller that talks to a
 * gateway without a template row in the database passes the options of that provider. Both say the
 * same three things, so a transport reads them without having to know which of the two it got.
 *
 * All three are nullable or empty tolerant because a free text message carries no template at all -
 * a gateway that requires one rejects it with SmsException::templateModeRequired rather than reading
 * an empty code.
 *
 * @internal
 */
#[Package('framework')]
interface TemplateSmsOptionsInterface extends MessageOptionsInterface
{
    /**
     * The code of the template as it was reported at the provider, e.g. SMS_123456789.
     */
    public function getTemplateCode(): ?string;

    /**
     * The signature the provider shows next to the message, e.g. the name of the shop.
     */
    public function getSignName(): ?string;

    /**
     * The values of the placeholders of the template, keyed by the name the provider knows them by.
     *
     * The key is a name as the provider writes it, which for a provider that knows its placeholders
     * by their position is a number - php turns such a key into an integer on the way in, so the
     * parameters are a map of either kind of key rather than one of strings only.
     *
     * @return array<array-key, string>
     */
    public function getParams(): array;
}
