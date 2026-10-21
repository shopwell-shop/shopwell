<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\SalesChannel;

use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnLineItem\OrderReturnLineItemEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnMessage\OrderReturnMessageEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * Turns a return into the structure the storefront and the administration render.
 *
 * The state is handed out as the technical name *and* its translated label: the storefront switches
 * on the name to decide which buttons to show, and prints the label, which is why neither of the two
 * can be derived from the other on the client.
 *
 * @final
 */
#[Package('after-sales')]
class OrderReturnPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(OrderReturnEntity $orderReturn): array
    {
        return [
            'id' => $orderReturn->id,
            'returnNumber' => $orderReturn->returnNumber,
            'orderId' => $orderReturn->orderId,
            // the customer recognises a case by the order number, not by an id; it stays null when the
            // caller did not load the order association
            'orderNumber' => $orderReturn->order?->getOrderNumber(),
            'type' => $orderReturn->type,
            'state' => $orderReturn->stateMachineState?->getTechnicalName(),
            'stateLabel' => $orderReturn->stateMachineState?->getTranslation('name'),
            'refundAmount' => $orderReturn->refundAmount,
            // the amount is the smallest unit of the currency, the decimals are what a template needs
            // to turn it back into the number a customer reads
            'decimals' => $orderReturn->order?->getCurrency()?->getItemRounding()?->getDecimals() ?? 2,
            'currency' => $orderReturn->currency,
            'reasonId' => $orderReturn->reasonId,
            'reasonLabel' => $orderReturn->reason?->getTranslation('label'),
            'reasonText' => $orderReturn->reasonText,
            'customerNote' => $orderReturn->customerNote,
            'evidence' => $orderReturn->evidence ?? [],
            'trackingCode' => $orderReturn->trackingCode,
            'trackingCarrier' => $orderReturn->trackingCarrier,
            'createdAt' => $orderReturn->getCreatedAt()?->format(\DATE_ATOM),
            'returnedAt' => $orderReturn->returnedAt?->format(\DATE_ATOM),
            'receivedAt' => $orderReturn->receivedAt?->format(\DATE_ATOM),
            'deadlineAt' => $orderReturn->deadlineAt?->format(\DATE_ATOM),
            'lineItems' => $this->lineItems($orderReturn),
            'messages' => $this->messages($orderReturn),
        ];
    }

    /**
     * @param iterable<OrderReturnEntity> $orderReturns
     *
     * @return list<array<string, mixed>>
     */
    public function presentList(iterable $orderReturns): array
    {
        $presented = [];

        foreach ($orderReturns as $orderReturn) {
            $presented[] = $this->present($orderReturn);
        }

        return $presented;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lineItems(OrderReturnEntity $orderReturn): array
    {
        $lineItems = [];

        foreach ($orderReturn->lineItems ?? [] as $lineItem) {
            if (!$lineItem instanceof OrderReturnLineItemEntity) {
                continue;
            }

            $orderLineItem = $lineItem->orderLineItem;

            $lineItems[] = [
                'id' => $lineItem->id,
                'orderLineItemId' => $lineItem->orderLineItemId,
                'label' => $orderLineItem?->getLabel(),
                'quantity' => $lineItem->quantity,
                'refundAmount' => $lineItem->refundAmount,
                'accepted' => $lineItem->accepted,
                'restocked' => $lineItem->restocked,
            ];
        }

        return $lineItems;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function messages(OrderReturnEntity $orderReturn): array
    {
        $messages = [];

        foreach ($orderReturn->messages ?? [] as $message) {
            if (!$message instanceof OrderReturnMessageEntity) {
                continue;
            }

            $messages[] = [
                'id' => $message->id,
                'authorRole' => $message->authorRole,
                'content' => $message->content,
                'attachments' => $message->attachments ?? [],
                'createdAt' => $message->getCreatedAt()?->format(\DATE_ATOM),
            ];
        }

        return $messages;
    }
}
