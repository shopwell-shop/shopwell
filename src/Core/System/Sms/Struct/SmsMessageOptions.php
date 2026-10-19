<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;

/**
 * Carries what the sms pipeline knows about a message next to the vendor specific options.
 *
 * Symfony hands the message itself to the events of the transports only, so the record of a delivery
 * reads the scene and the template off these options - the transport of a provider on the other hand
 * finds the sign name, the template code and the parameters it has to submit here.
 *
 * A message that goes out as free text carries the scene and the template name only, templateCode
 * stays empty and the subject is the rendered body.
 *
 * A message that could not be routed names the reason it was not sent, which is the only thing the
 * delivery record can report in that case: the message was built and handled, it simply had no
 * gateway to go out over.
 *
 * @internal
 */
#[Package('framework')]
final class SmsMessageOptions implements TemplateSmsOptionsInterface
{
    /**
     * @param array<string, string> $params the parameters the provider expects
     * @param string|null $undeliverableReason the error code of the module that explains why no
     *                                         gateway took the message, null when one did
     */
    public function __construct(
        private readonly string $scene = SmsTemplateEntity::SCENE_TRANSACTION,
        private readonly ?string $templateRef = null,
        private readonly ?string $templateCode = null,
        private readonly ?string $signName = null,
        private readonly array $params = [],
        private readonly ?string $undeliverableReason = null,
    ) {
    }

    public function getScene(): string
    {
        return $this->scene;
    }

    public function getTemplateRef(): ?string
    {
        return $this->templateRef;
    }

    public function getTemplateCode(): ?string
    {
        return $this->templateCode;
    }

    public function getSignName(): ?string
    {
        return $this->signName;
    }

    /**
     * @return array<string, string>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Whether the provider sends a reported template instead of a free text body.
     */
    public function isTemplateBased(): bool
    {
        return $this->templateCode !== null && $this->templateCode !== '';
    }

    /**
     * The error code of the module that explains why no gateway took the message, null when one did.
     *
     * It is read by the delivery record, which writes it as the failure of the attempt instead of
     * reporting a message that never left the shop as sent.
     */
    public function getUndeliverableReason(): ?string
    {
        return $this->undeliverableReason;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'scene' => $this->scene,
            'templateRef' => $this->templateRef,
            'templateCode' => $this->templateCode,
            'signName' => $this->signName,
            'params' => $this->params === [] ? null : $this->params,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function getRecipientId(): ?string
    {
        return null;
    }
}
