<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Alipay;

use Payum\Core\Exception\RequestNotSupportedException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferRequest;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;

/**
 * Submits a payout to alipay, `alipay.fund.trans.uni.transfer`.
 *
 * Alipay answers `code=10000` for a call it accepted - which is not a payout that succeeded: the
 * fate of the money is the `status` next to it, and only `SUCCESS` is final for the moment, `DEALING`
 * and `REFUND` are the answers the query and the ant message of the interface resolve later. A
 * rejected call is an answer as well: `SYSTEM_ERROR` and `PAYMENT_FAIL` name transfers whose fate
 * only a query decides, everything else is a failure the codes name themselves - the money codes of
 * this interface carry no `ACQ.` prefix, which belongs to the acquiring codes.
 *
 * There is no `notify_url` on this interface: the result arrives through the ant message
 * `alipay.fund.trans.order.changed` on the application gateway. The request carries the model the
 * driver prepared with `PaymentGatewayModel::forTransfer()` - the record of the attempt and the
 * {@see TransferResult} live on it, the driver reads them the way it reads the model of a payment
 * order.
 *
 * The identity of the recipient is stored encrypted; the action decrypts it with the credential
 * cipher right before the request is built, so the plain text never lives longer than one call.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayTransferAction extends AbstractPaymentAction
{
    public const PRODUCT_CODES = [
        'alipay_user_id' => self::PRODUCT_CODE_ACCOUNT,
        'alipay_logon_id' => self::PRODUCT_CODE_ACCOUNT,
        'alipay_open_id' => self::PRODUCT_CODE_ACCOUNT,
        'bankcard' => self::PRODUCT_CODE_BANKCARD,
    ];

    public const BIZ_SCENE = 'DIRECT_TRANSFER';
    /**
     * The identity types of the recipients of alipay, mapped to the `identity_type` of the wire
     * format. A card is a `BANKCARD_ACCOUNT` - `BANKCARD_NO` does not exist on this interface.
     */
    private const IDENTITY_TYPES = [
        'alipay_user_id' => 'ALIPAY_USER_ID',
        'alipay_logon_id' => 'ALIPAY_LOGON_ID',
        'alipay_open_id' => 'ALIPAY_OPEN_ID',
        'bankcard' => 'BANKCARD_ACCOUNT',
    ];

    /**
     * To an account the transfer moves without a password, to a card through the bankcard product.
     * The query of a transfer needs the very same pair, which is why both actions read it here.
     */
    private const PRODUCT_CODE_ACCOUNT = 'TRANS_ACCOUNT_NO_PWD';

    private const PRODUCT_CODE_BANKCARD = 'TRANS_BANKCARD_NO_PWD';

    /**
     * The keys of the `bankcard_ext_info` the interface knows - only the ones the recipient carries
     * are sent, alipay rejects nothing for a missing one of the optional details.
     */
    private const BANKCARD_INFO_KEYS = ['account_type', 'inst_name', 'inst_province', 'inst_city', 'inst_branch_name', 'bank_code'];

    private const MAX_ORDER_TITLE_LENGTH = 40;

    /**
     * A company account pays out from 50000 yuan on only with a `remark` - without one the interface
     * answers `MEMO_REQUIRED_IN_TRANSFER_ERROR`, and that is a burned out business number: the number
     * is used then, a retry has to leave with a new one. Refusing the call here is cheaper than
     * losing the number at the interface.
     */
    private const REMARK_REQUIRED_AMOUNT = 5_000_000;

    /**
     * The codes of a rejection that are no failure: alipay itself demands the same out business
     * number to be sent again after the interface is asked which fate the transfer met.
     */
    private const RETRY_CODES = ['SYSTEM_ERROR', 'PAYMENT_FAIL'];

    protected string $apiClass = AlipayApi::class;

    public function __construct(private readonly PaymentCredentialCipher $cipher)
    {
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        if (!$request instanceof PaymentTransferRequest) {
            throw RequestNotSupportedException::create($request);
        }

        $api = $this->api();
        $model = $this->modelOf($request);

        // the subject comes from the model - the driver prepared it with forTransfer(), the channel
        // does not derive it a second time from the entity
        $subject = $model->offsetExists(PaymentGatewayModel::SUBJECT) && \is_scalar($model->offsetGet(PaymentGatewayModel::SUBJECT))
            ? (string) $model->offsetGet(PaymentGatewayModel::SUBJECT)
            : '';

        $content = self::contentOf($request->transfer, $request->recipient, $request->channelParams, $this->cipher, $subject);

        $this->recordRequest($model, ['method' => AlipayApi::METHOD_UNI_TRANSFER, 'content' => $content]);

        try {
            $answer = $api->transfer($content);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage(), $exception);
        }

        $this->recordResponse($model, $answer);

        $this->setResult($model, self::resultOf($answer));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, PaymentTransferRequest::class);
    }

    /**
     * The business content of a payout, in the snake_case of the wire format.
     *
     * The scene of the payout is reported with the call - the name of the scene was applied for at
     * alipay before the first payout, the report infos are the per case evidence alipay demands for
     * the scene and are passed on unchanged: what they contain is a decision of the business case,
     * the platform does not guess it.
     *
     * The subject comes from the model, not from the entities - the model is the one source of the
     * payout the record and the wire content are built from, so they can not tell different stories.
     * Only the cut to the 40 characters of the wire format happens here, because that limit is
     * alipay's, not a rule of the platform.
     *
     * @param array<string, mixed> $channelParams
     *
     * @throws PaymentException
     *
     * @return array<string, mixed>
     */
    public static function contentOf(
        PaymentTransferEntity $transfer,
        PaymentTransferRecipientEntity $recipient,
        array $channelParams,
        PaymentCredentialCipher $cipher,
        string $subject,
    ): array {
        if (!isset(self::IDENTITY_TYPES[$recipient->identityType])) {
            throw PaymentException::transferIdentityNotSupported($transfer->provider, $recipient->identityType);
        }

        $sceneName = $channelParams['transfer_scene_name'] ?? null;

        if (!\is_string($sceneName) || trim($sceneName) === '') {
            // alipay rejects the call without it - a payout that cannot be sent must not be built
            throw PaymentException::missingParameter('channel_params.transfer_scene_name');
        }

        $content = [
            'out_biz_no' => $transfer->outNo,
            'trans_amount' => self::amount($transfer->amount),
            'product_code' => self::PRODUCT_CODES[$recipient->identityType],
            'biz_scene' => self::BIZ_SCENE,
            'order_title' => mb_substr($subject !== '' ? $subject : PaymentGatewayModel::DEFAULT_SUBJECT, 0, self::MAX_ORDER_TITLE_LENGTH),
            'transfer_scene_name' => $sceneName,
            'payee_info' => self::payeeOf($recipient, $cipher),
        ];

        // the report infos ride along the way the caller filed them - alipay validates their shape
        $reportInfos = $channelParams['transfer_scene_report_infos'] ?? null;

        if (\is_array($reportInfos)) {
            $content['transfer_scene_report_infos'] = $reportInfos;
        }

        $remark = $channelParams['remark'] ?? null;

        if (\is_string($remark) && trim($remark) !== '') {
            $content['remark'] = $remark;
        }

        if ($transfer->amount >= self::REMARK_REQUIRED_AMOUNT && !isset($content['remark'])) {
            throw PaymentException::missingParameter('channel_params.remark');
        }

        return $content;
    }

    /**
     * The answer of the interface, narrowed to what a transfer means.
     *
     * Pure on purpose: the same answer arrives at every retry and every query, and what it means is
     * decided here and nowhere else.
     *
     * @param array<string, mixed> $answer
     */
    public static function resultOf(array $answer): TransferResult
    {
        $code = self::stringOf($answer, 'code');

        if ($code !== '' && $code !== AlipayApi::TRANSFER_CALL_OK) {
            // the interface names the case in the sub code, the code of the call stays a generic one
            $errorCode = self::stringOf($answer, 'sub_code') ?: $code;

            if (\in_array($errorCode, self::RETRY_CODES, true)) {
                // alipay names the very out business number to be sent again - the fate of the
                // transfer is unknown until the query of the interface has spoken
                return new TransferResult(TransferResult::STATE_UNKNOWN);
            }

            return new TransferResult(
                TransferResult::STATE_FAILED,
                failureCode: $errorCode,
            );
        }

        $status = self::stringOf($answer, 'status');
        $subCode = self::stringOf($answer, 'sub_code');

        return new TransferResult(
            self::stateOf($status),
            providerRef: self::stringOf($answer, 'order_id') ?: null,
            providerStageRef: self::stringOf($answer, 'pay_fund_order_id') ?: null,
            failureCode: $status === 'FAIL'
                ? ($subCode !== '' ? $subCode : 'FAIL')
                : null,
        );
    }

    /**
     * Alipay expects the amount in yuan with two decimals, the platform stores it in fen.
     */
    public static function amount(int $minor): string
    {
        return intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', \STR_PAD_LEFT);
    }

    protected function api(): AlipayApi
    {
        $api = parent::api();
        \assert($api instanceof AlipayApi);

        return $api;
    }

    private static function stateOf(string $status): string
    {
        return match ($status) {
            'SUCCESS' => TransferResult::STATE_SUCCEEDED,
            'FAIL' => TransferResult::STATE_FAILED,
            'DEALING' => TransferResult::STATE_PROCESSING,
            'REFUND' => TransferResult::STATE_RETURNED,
            default => TransferResult::STATE_UNKNOWN,
        };
    }

    /**
     * @throws PaymentException
     *
     * @return array<string, mixed>
     */
    private static function payeeOf(PaymentTransferRecipientEntity $recipient, PaymentCredentialCipher $cipher): array
    {
        $payee = [
            'identity_type' => self::IDENTITY_TYPES[$recipient->identityType],
            // the identity is a credential at rest and is decrypted for this call alone
            'identity' => $cipher->decryptValue($recipient->identityValue),
        ];

        $holderName = $recipient->holderName ?? '';

        switch ($recipient->identityType) {
            case 'alipay_user_id':
                // alipay checks the name when it is sent, but does not demand it for a uid
                if ($holderName !== '') {
                    $payee['name'] = $holderName;
                }

                break;
            case 'alipay_logon_id':
            case 'bankcard':
                // a logon id is a phone number or an email: without the name alipay would pay the
                // first account it finds, which is why the interface refuses the call without it
                if ($holderName === '') {
                    throw PaymentException::missingParameter('payee_info.name');
                }

                $payee['name'] = $holderName;

                if ($recipient->identityType === 'bankcard') {
                    $ext = self::bankcardExtInfoOf($recipient);

                    if ($ext !== []) {
                        $payee['bankcard_ext_info'] = $ext;
                    }
                }

                break;
        }

        return $payee;
    }

    /**
     * @return array<string, mixed>
     */
    private static function bankcardExtInfoOf(PaymentTransferRecipientEntity $recipient): array
    {
        $ext = [];

        foreach (self::BANKCARD_INFO_KEYS as $key) {
            $value = $recipient->bankInfo[$key] ?? null;

            if (\is_scalar($value) && (string) $value !== '') {
                $ext[$key] = $value;
            }
        }

        return $ext;
    }

    /**
     * @param array<string, mixed> $answer
     */
    private static function stringOf(array $answer, string $key): string
    {
        $value = $answer[$key] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }
}
