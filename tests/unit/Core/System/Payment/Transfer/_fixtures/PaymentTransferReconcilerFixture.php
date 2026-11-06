<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer\_fixtures;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferReconciler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStateHandler;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Tests\Unit\Core\System\Payment\Account\_fixtures\PaymentAccountLockStub;
use Shopwell\Tests\Unit\Core\System\Payment\NumberRangeFixture;
use Shopwell\Tests\Unit\Core\System\Payment\Transfer\PaymentTransferRulesFixture;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class PaymentTransferReconcilerFixture extends TestCase
{
    private const SECRET = 'unit-test-application-secret';

    /**
     * @param EntityRepository<PaymentTransferCollection> $transferRepository
     * @param EntityRepository<StateMachineStateCollection> $stateRepository
     */
    public static function reconciler(EntityRepository $transferRepository, EntityRepository $stateRepository): PaymentTransferReconciler
    {
        $providers = new PaymentProviderRegistry([]);

        $configResolver = new PaymentConfigResolver(
            $providers,
            new PaymentProductRegistry($providers),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            new PaymentCredentialCipher(self::SECRET),
            static::createStub(EntityRepository::class),
        );

        $recipients = static::createStub(EntityRepository::class);
        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn('state-created');

        $transferService = new PaymentTransferService(
            $transferRepository,
            $recipients,
            new PaymentTransferStateHandler(static::createStub(StateMachineRegistry::class)),
            new PaymentTransferRecipientService($recipients, new PaymentCredentialCipher(self::SECRET), new MockClock('2026-10-18T09:00:00+00:00'), PaymentTransferRulesFixture::registry()),
            new PaymentCredentialCipher(self::SECRET),
            $initialStateIdLoader,
            new MockClock('2026-10-18T09:00:00+00:00'),
            PaymentTransferRulesFixture::registry(),
            NumberRangeFixture::generator('PT1001'),
            $configResolver,
            new PaymentAccountLockStub(),
        );

        return new PaymentTransferReconciler(
            $configResolver,
            $transferService,
            $transferRepository,
            $stateRepository,
            new MockClock('2026-10-18T09:00:00+00:00'),
            static::createStub(LoggerInterface::class),
        );
    }

    public static function state(string $technicalName): StateMachineStateEntity
    {
        $state = new StateMachineStateEntity();
        $state->setId('state-' . $technicalName);
        $state->setTechnicalName($technicalName);

        return $state;
    }
}
