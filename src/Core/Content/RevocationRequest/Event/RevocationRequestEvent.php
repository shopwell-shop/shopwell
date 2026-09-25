<?php declare(strict_types=1);

namespace Shopwell\Core\Content\RevocationRequest\Event;

use Shopwell\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopwell\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\FormDataObjectType;
use Shopwell\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Event\MailAware;
use Shopwell\Core\Framework\Event\SalesChannelAware;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final class RevocationRequestEvent extends Event implements SalesChannelAware, MailAware, ScalarValuesAware, FlowEventAware
{
    public const EVENT_NAME = 'revocation_request.sent';

    /**
     * @var array<int|string, mixed>
     */
    private readonly array $formData;

    public function __construct(
        private readonly Context $context,
        private readonly string $salesChannelId,
        private readonly MailRecipientStruct $recipients,
        DataBag $formDataBag
    ) {
        $this->formData = $formDataBag->all();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(FlowMailVariables::REVOCATION_REQUEST_FORM_DATA, new FormDataObjectType());
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return $this->recipients;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getValues(): array
    {
        return [
            FlowMailVariables::REVOCATION_REQUEST_FORM_DATA => $this->formData,
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getRevocationRequestFormData(): array
    {
        return $this->formData;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
