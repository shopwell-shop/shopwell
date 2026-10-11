<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Event\EventData;

use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class MailRecipientStruct
{
    private ?string $bcc = null;

    private ?string $cc = null;

    /**
     * @param array<string, mixed> $recipients ['email' => 'name']
     */
    public function __construct(private array $recipients)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    /**
     * @param array<string, mixed> $recipients
     */
    public function setRecipients(array $recipients): void
    {
        $this->recipients = $recipients;
    }

    public function getBcc(): ?string
    {
        return $this->bcc;
    }

    public function setBcc(?string $bcc): void
    {
        $this->bcc = $bcc;
    }

    public function getCc(): ?string
    {
        return $this->cc;
    }

    public function setCc(?string $cc): void
    {
        $this->cc = $cc;
    }
}
