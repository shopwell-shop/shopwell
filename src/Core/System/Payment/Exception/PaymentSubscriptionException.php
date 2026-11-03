<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class PaymentSubscriptionException extends PaymentException
{
    /**
     * A subscription plan whose monthly deduction day falls after the 28th cannot be signed up at
     * alipay at all: `period_rule_params.execute_time` is capped there by the provider. Refusing
     * at plan level beats silently moving the deduction day - the subscriber would be charged on
     * a day they never agreed to.
     */
    public static function paymentCycleNotExpressible(string $provider, string $unit, int $count, int $anchorDayOfMonth): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::CYCLE_NOT_EXPRESSIBLE,
            'A subscription charging every {{ count }} {{ unit }} on day {{ anchorDayOfMonth }} of the month cannot be expressed at the provider "{{ provider }}": monthly deduction days are limited to the 28th there.',
            ['provider' => $provider, 'unit' => $unit, 'count' => $count, 'anchorDayOfMonth' => $anchorDayOfMonth]
        );
    }

    public static function paymentCycleNotFound(string $id): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CYCLE_NOT_FOUND,
            'The subscription cycle "{{ id }}" does not exist.',
            ['id' => $id]
        );
    }

    /**
     * The insert of a cycle hit the unique key over (subscription, cycle number), but the
     * conflicting row cannot be read back in this request - the cycle exists, it is just not
     * visible yet, so retrying is the right answer and "not found" would be a lie.
     */
    public static function paymentCycleConflict(string $subscriptionId, int $cycleNo): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::CYCLE_CONFLICT,
            'The cycle {{ cycleNo }} of the subscription "{{ subscriptionId }}" conflicts with an existing cycle that cannot be read back.',
            ['subscriptionId' => $subscriptionId, 'cycleNo' => $cycleNo]
        );
    }

    /**
     * Only a cycle the channel answered definitively for may be re-armed: an in-flight trade has
     * to stay `unknown` until a query resolves it, and a settled cycle has nothing to re-arm.
     */
    public static function paymentCycleNotRearmable(string $id, string $state): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::CYCLE_NOT_REARMABLE,
            'The subscription cycle "{{ id }}" is "{{ state }}" and cannot be re-armed for another charge attempt.',
            ['id' => $id, 'state' => $state]
        );
    }

    public static function subscriptionNotFound(string $id): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::SUBSCRIPTION_NOT_FOUND,
            'The payment subscription "{{ id }}" does not exist.',
            ['id' => $id]
        );
    }

    public static function invalidPeriodUnit(string $unit): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_PERIOD_UNIT,
            'The period unit "{{ unit }}" is not one of day, week, month or year.',
            ['unit' => $unit]
        );
    }

    public static function invalidPeriodCount(int $count): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_PERIOD_COUNT,
            'A period must advance by at least 1, got {{ count }}.',
            ['count' => $count]
        );
    }
}
