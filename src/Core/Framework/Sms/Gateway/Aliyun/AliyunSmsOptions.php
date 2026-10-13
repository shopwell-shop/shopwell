<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Gateway\Aliyun;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\Struct\TemplateSmsOptionsInterface;

/**
 * The aliyun specific options of a message.
 *
 * The pipeline builds these itself out of the template mapping, so a message that went through
 * SmsSender carries them already. They are public because they are also the way a caller passes the
 * data of a reported template to the gateway directly, without a template row in the database.
 *
 * @internal
 */
#[Package('framework')]
final class AliyunSmsOptions implements TemplateSmsOptionsInterface
{
    /**
     * @param array<string, string> $params the values of the placeholders of the reported template
     * @param string|null $outId the identifier aliyun echoes back in the receipt, for correlating a
     *                           delivery with a message of the shop
     */
    public function __construct(
        private readonly ?string $templateCode = null,
        private readonly ?string $signName = null,
        private readonly array $params = [],
        private readonly ?string $outId = null,
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
     * @return array<string, string>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getOutId(): ?string
    {
        return $this->outId;
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
            'outId' => $this->outId,
            'params' => $this->params === [] ? null : $this->params,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function getRecipientId(): ?string
    {
        return null;
    }
}
