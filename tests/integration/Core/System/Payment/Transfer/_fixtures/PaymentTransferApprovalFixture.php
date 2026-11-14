<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Transfer\_fixtures;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('framework')]
final class PaymentTransferApprovalFixture
{
    /**
     * @param array<string, string>|null $credentials
     */
    public static function create(ContainerInterface $container, ?array $credentials = null): PaymentTransferEntity
    {
        $context = Context::createDefaultContext();
        $appId = Uuid::randomHex();
        $recipientId = Uuid::randomHex();
        $transferId = Uuid::randomHex();
        $accountId = Uuid::randomHex();
        $container->get('payment_account.repository')->create([[
            'id' => $accountId,
            'providerTechnicalName' => 'alipay',
            'name' => 'Transfer claim account',
            'enabledProducts' => ['alipay_h5'],
            'active' => true,
            'credentials' => $credentials,
        ]], $context);
        $cipher = $container->get(PaymentCredentialCipher::class);
        $container->get('payment_app.repository')->create([[
            'id' => $appId,
            'technicalName' => 'transfer_claim_' . $appId,
            'name' => 'Transfer claim test',
            'type' => PaymentAppEntity::TYPE_EXTERNAL,
            'appSecret' => $cipher->encryptValue('secret'),
            'active' => true,
        ]], $context);
        $container->get('payment_transfer_recipient.repository')->create([[
            'id' => $recipientId,
            'paymentAppId' => $appId,
            'provider' => 'alipay',
            'identityType' => 'alipay_user_id',
            'identityValue' => $cipher->encryptValue('recipient'),
            'identityHash' => hash('sha256', $recipientId),
            'onboardingStatus' => PaymentTransferStates::ONBOARDING_REGISTERED,
            'payoutEnabled' => true,
        ]], $context);
        $container->get('payment_transfer.repository')->create([[
            'id' => $transferId,
            'paymentAppId' => $appId,
            'transferNo' => $transferId,
            'accountId' => $accountId,
            'outNo' => substr($transferId, 0, 30),
            'recipientId' => $recipientId,
            'provider' => 'alipay',
            'channel' => 'alipay_h5',
            'amount' => 100,
            'currency' => 'CNY',
            'statusId' => $container->get(InitialStateIdLoader::class)->get(PaymentTransferStates::STATE_MACHINE),
            'submitAttempts' => 0,
        ]], $context);

        return $container->get(PaymentTransferService::class)->getById($transferId);
    }
}
