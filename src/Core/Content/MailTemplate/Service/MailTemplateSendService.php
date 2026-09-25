<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MailTemplate\Service;

use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Content\Mail\Payload\MailPayload;
use Shopwell\Core\Content\Mail\Service\AbstractMailService;
use Shopwell\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopwell\Core\Content\MailTemplate\MailTemplateEntity;
use Shopwell\Core\Content\MailTemplate\Request\GetDataAndSendRequest;
use Shopwell\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[Package('after-sales')]
class MailTemplateSendService
{
    public function __construct(
        private readonly AbstractMailService $mailService,
        private readonly MailDataProvider $mailDataProvider,
    ) {
    }

    public function getTemplateDataAndSend(
        GetDataAndSendRequest $request,
        Context $context,
    ): ?Email {
        $templateData = $this->mailDataProvider->getTemplateData(
            $request->mailTemplate,
            $request->entityMapping,
            $context,
            $request->templateData
        );

        return $this->send($request->mailPayload, $context, $templateData, $request->mailTemplate);
    }

    /**
     * @param array<string|int,mixed> $templateData
     */
    public function send(
        MailPayload $mailPayload,
        Context $context,
        array $templateData,
        ?MailTemplateEntity $mailTemplate = null,
    ): ?Email {
        $data = $mailPayload->toArray();

        $extension = new MailSendSubscriberConfig(
            false,
            $mailPayload->documentIds,
            $mailPayload->mediaIds,
        );

        $orderId = null;
        if (\array_key_exists('order', $templateData)) {
            if (\is_array($templateData['order'])) {
                $orderId = $templateData['order']['id'] ?? null;
            } elseif ($templateData['order'] instanceof OrderEntity) {
                $orderId = $templateData['order']->getId();
            }
        }

        $data['attachmentsConfig'] = new MailAttachmentsConfig(
            $context,
            $mailTemplate ?? new MailTemplateEntity(),
            $extension,
            [],
            $orderId,
        );

        return $this->mailService->send($data, $context, $templateData);
    }
}
