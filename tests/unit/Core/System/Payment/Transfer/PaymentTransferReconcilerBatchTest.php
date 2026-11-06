<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferReconciler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Tests\Unit\Core\System\Payment\Transfer\_fixtures\PaymentTransferReconcilerFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferReconciler::class)]
class PaymentTransferReconcilerBatchTest extends TestCase
{
    public function testRunAsksTheInterfacesAboutTheDuePayouts(): void
    {
        // the state lookup is the first read of the batch, the transfer search the second - both are
        // the proof the handler ran the reconciliation and not something else
        $stateRepository = static::createMock(EntityRepository::class);
        $stateRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'state_machine_state',
                4,
                new StateMachineStateCollection([
                    PaymentTransferReconcilerFixture::state(PaymentTransferStates::STATE_SUBMITTED),
                    PaymentTransferReconcilerFixture::state(PaymentTransferStates::STATE_PROCESSING),
                    PaymentTransferReconcilerFixture::state(PaymentTransferStates::STATE_AWAITING_USER_CONFIRM),
                    PaymentTransferReconcilerFixture::state(PaymentTransferStates::STATE_SUCCEEDED),
                ]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ));

        $transferRepository = static::createMock(EntityRepository::class);
        $transferRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                PaymentTransferEntity::ENTITY_NAME,
                0,
                new PaymentTransferCollection([]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ));

        PaymentTransferReconcilerFixture::reconciler($transferRepository, $stateRepository)->reconcileDue();
    }
}
