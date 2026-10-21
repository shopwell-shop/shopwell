<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('after-sales')]
class OrderReturnException extends HttpException
{
    final public const RETURN_DISABLED_CODE = 'CHECKOUT__RETURN_DISABLED';
    final public const RETURN_NOT_FOUND_CODE = 'CHECKOUT__RETURN_NOT_FOUND';
    final public const RETURN_ORDER_NOT_FOUND_CODE = 'CHECKOUT__RETURN_ORDER_NOT_FOUND';
    final public const RETURN_ORDER_NOT_FINISHED_CODE = 'CHECKOUT__RETURN_ORDER_NOT_FINISHED';
    final public const RETURN_WINDOW_EXPIRED_CODE = 'CHECKOUT__RETURN_WINDOW_EXPIRED';
    final public const RETURN_INVALID_TYPE_CODE = 'CHECKOUT__RETURN_INVALID_TYPE';
    final public const RETURN_EMPTY_POSITIONS_CODE = 'CHECKOUT__RETURN_EMPTY_POSITIONS';
    final public const RETURN_LINE_ITEM_UNKNOWN_CODE = 'CHECKOUT__RETURN_LINE_ITEM_UNKNOWN';
    final public const RETURN_QUANTITY_EXCEEDED_CODE = 'CHECKOUT__RETURN_QUANTITY_EXCEEDED';
    final public const RETURN_REASON_UNKNOWN_CODE = 'CHECKOUT__RETURN_REASON_UNKNOWN';
    final public const RETURN_GUEST_ACCESS_DENIED_CODE = 'CHECKOUT__RETURN_GUEST_ACCESS_DENIED';
    final public const RETURN_STATE_NOT_ALLOWED_CODE = 'CHECKOUT__RETURN_STATE_NOT_ALLOWED';
    final public const RETURN_REFUND_NOT_POSSIBLE_CODE = 'CHECKOUT__RETURN_REFUND_NOT_POSSIBLE';
    final public const RETURN_MISSING_TRACKING_CODE_CODE = 'CHECKOUT__RETURN_MISSING_TRACKING_CODE';
    final public const RETURN_EMPTY_MESSAGE_CODE = 'CHECKOUT__RETURN_EMPTY_MESSAGE';

    public static function disabled(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::RETURN_DISABLED_CODE,
            'Returns are not enabled for this sales channel.'
        );
    }

    public static function notFound(string $orderReturnId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::RETURN_NOT_FOUND_CODE,
            'The return "{{ orderReturnId }}" does not exist.',
            ['orderReturnId' => $orderReturnId]
        );
    }

    public static function orderNotFound(string $orderNumber): self
    {
        // the message must not tell an anonymous visitor whether an order exists, the code alone is
        // what the storefront reacts to
        return new self(
            Response::HTTP_NOT_FOUND,
            self::RETURN_ORDER_NOT_FOUND_CODE,
            'No order matches the given number and email address.',
            ['orderNumber' => $orderNumber]
        );
    }

    public static function orderNotFinished(string $orderNumber): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::RETURN_ORDER_NOT_FINISHED_CODE,
            'The order "{{ orderNumber }}" can not be returned yet, it was neither shipped nor completed.',
            ['orderNumber' => $orderNumber]
        );
    }

    public static function windowExpired(string $orderNumber, int $days): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::RETURN_WINDOW_EXPIRED_CODE,
            'The return window of {{ days }} days of the order "{{ orderNumber }}" is over.',
            ['orderNumber' => $orderNumber, 'days' => $days]
        );
    }

    public static function invalidType(string $type): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::RETURN_INVALID_TYPE_CODE,
            'The return type "{{ type }}" is unknown.',
            ['type' => $type]
        );
    }

    public static function emptyPositions(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::RETURN_EMPTY_POSITIONS_CODE,
            'An return needs at least one position.'
        );
    }

    public static function lineItemUnknown(string $lineItemId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::RETURN_LINE_ITEM_UNKNOWN_CODE,
            'The line item "{{ lineItemId }}" does not belong to the order of this return.',
            ['lineItemId' => $lineItemId]
        );
    }

    public static function quantityExceeded(string $lineItemId, int $requested, int $available): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::RETURN_QUANTITY_EXCEEDED_CODE,
            'Only {{ available }} of the line item "{{ lineItemId }}" can still be returned, {{ requested }} were requested.',
            ['lineItemId' => $lineItemId, 'requested' => $requested, 'available' => $available]
        );
    }

    public static function reasonUnknown(string $reasonId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::RETURN_REASON_UNKNOWN_CODE,
            'The return reason "{{ reasonId }}" does not exist or is not active.',
            ['reasonId' => $reasonId]
        );
    }

    public static function guestAccessDenied(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::RETURN_GUEST_ACCESS_DENIED_CODE,
            'The guest token is invalid or the return does not belong to it.'
        );
    }

    public static function stateNotAllowed(string $state, string $action): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::RETURN_STATE_NOT_ALLOWED_CODE,
            'The action "{{ action }}" can not be run from the state "{{ state }}".',
            ['state' => $state, 'action' => $action]
        );
    }

    public static function refundNotPossible(string $orderReturnNumber, string $reason): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::RETURN_REFUND_NOT_POSSIBLE_CODE,
            'The return "{{ returnNumber }}" can not be refunded: {{ reason }}',
            ['returnNumber' => $orderReturnNumber, 'reason' => $reason]
        );
    }

    public static function missingTrackingCode(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::RETURN_MISSING_TRACKING_CODE_CODE,
            'A return can only be announced with a tracking code.'
        );
    }

    public static function emptyMessage(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::RETURN_EMPTY_MESSAGE_CODE,
            'A message of a return can not be empty.'
        );
    }
}
