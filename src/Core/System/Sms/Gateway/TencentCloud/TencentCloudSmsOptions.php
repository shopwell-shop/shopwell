<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\TencentCloud;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Struct\TemplateSmsOptionsInterface;

/**
 * The tencent cloud specific options of a message.
 *
 * The pipeline builds these itself out of the template mapping, so a message that went through
 * SmsSender carries them already. They are public because they are also the way a caller passes the
 * data of a reported template to the gateway directly, without a template row in the database.
 *
 * The parameters are named after their position in the template - tencent cloud knows the
 * placeholders of a template only by their number, so the names decide which value fills which one.
 *
 * @internal
 */
#[Package('framework')]
final class TencentCloudSmsOptions implements TemplateSmsOptionsInterface
{
    /**
     * @param array<array-key, string> $params keyed by the number the placeholder has in the template
     * @param string|null $sessionContext echoed back in the delivery receipt of tencent cloud
     */
    public function __construct(
        private readonly ?string $templateCode = null,
        private readonly ?string $signName = null,
        private readonly array $params = [],
        private readonly ?string $sessionContext = null,
    ) {
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
     * The parameters of the template, keyed by the number the placeholder has in it.
     *
     * @return array<array-key, string>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getSessionContext(): ?string
    {
        return $this->sessionContext;
    }

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
            'templateCode' => $this->templateCode,
            'signName' => $this->signName,
            'sessionContext' => $this->sessionContext,
            'params' => $this->params === [] ? null : $this->params,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function getRecipientId(): ?string
    {
        return null;
    }
}
