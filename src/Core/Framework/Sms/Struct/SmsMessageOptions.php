<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsTemplateEntity;

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
 * @internal
 */
#[Package('framework')]
final class SmsMessageOptions implements TemplateSmsOptionsInterface
{
    /**
     * @param array<string, string> $params the parameters the provider expects
     */
    public function __construct(
        private readonly string $scene = SmsTemplateEntity::SCENE_TRANSACTION,
        private readonly ?string $templateRef = null,
        private readonly ?string $templateCode = null,
        private readonly ?string $signName = null,
        private readonly array $params = [],
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
