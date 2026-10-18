<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Wechat;

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
use WeChatPay\Crypto\Rsa;

/**
 * Submits a merchant transfer to wechat, `POST /v3/fund-app/mch-transfer/transfer-bills`.
 *
 * This is the product of 2025-01-15, which pays out to the openid of a user of the very appid. The
 * account a payout is sent from is the operations account of the merchant, which can not be closed
 * again once it is opened.
 *
 * **The shopper has to accept the transfer.** The submission of a transfer wechat wants a confirmation
 * for answers `WAIT_USER_CONFIRM` and hands out a `package_info`: the shopper's wechat app shows the
 * transfer and only accepts it there, through `wx.requestMerchantTransfer` of the mini program sdk
 * (base library 3.3.0 and up). It does not work in an embedded H5 or a webview, and it does not work
 * from a desktop client - the shopper has to be in the wechat app. The `success` callback of
 * `wx.requestMerchantTransfer` is no evidence that the money moved: it says the shopper accepted, and
 * the transfer is on its way afterwards, which is why the platform maps the state to
 * `awaiting_confirm` and reads the rest from the query. A bill nobody accepts closes after 24 hours,
 * the moment is carried by the `expire_at` of the transfer
 * ({@see \Shopwell\Core\System\Payment\Transfer\PaymentTransferService}).
 *
 * The `package_info` is single use and can not be recovered from the interface: the platform stores it
 * in `confirm_payload`, and losing it means the transfer can only be canceled and sent again. The
 * action therefore passes it on untouched - it is never logged, cut or copied into another field.
 *
 * The identity of the recipient is stored encrypted; the action decrypts it with the credential cipher
 * right before the request is built, so the plain text never lives longer than one call. Only an
 * openid of this appid can be paid out to - wechat has no other recipient type for a merchant
 * transfer.
 *
 * From 2000 yuan on the interface demands the name of the recipient next to the openid. The name is
 * RSA encrypted with the platform certificate, and the request names that certificate in the
 * `Wechatpay-Serial` header - the header and the encrypted field only exist together. Below the
 * threshold neither is sent.
 *
 * There is no `notify_url`: the notifications of an api v3 merchant transfer are app level
 * (`MCHTRANSFER.BILL.FINISHED`) and not addressed per transfer, so the routing of them belongs to the
 * notification stage and not to this call.
 *
 * The request carries the model the driver prepared with `PaymentGatewayModel::forTransfer()` - the
 * record of the attempt and the {@see TransferResult} live on it, the driver reads them the way it
 * reads the model of a payment order.
 *
 * @internal
 */
#[Package('framework')]
final class WechatTransferAction extends AbstractPaymentAction
{
    /**
     * A merchant transfer pays out to an openid of the appid of the channel and to nothing else.
     */
    final public const IDENTITY_TYPE_OPEN_ID = 'wechat_open_id';

    /**
     * The channel parameter naming the scene of the transfer. The scene is applied for in the merchant
     * platform before the first payout and decides the evidence the interface demands with every call.
     */
    final public const PARAM_TRANSFER_SCENE_ID = 'transfer_scene_id';

    /**
     * The channel parameter carrying the per case evidence of the scene, as the caller filed it - the
     * platform passes it through and does not guess what the business case has to prove.
     */
    final public const PARAM_TRANSFER_SCENE_REPORT_INFOS = 'transfer_scene_report_infos';

    final public const BILL_STATE_ACCEPTED = 'ACCEPTED';
    final public const BILL_STATE_PROCESSING = 'PROCESSING';
    final public const BILL_STATE_WAIT_USER_CONFIRM = 'WAIT_USER_CONFIRM';
    final public const BILL_STATE_TRANSFERING = 'TRANSFERING';
    final public const BILL_STATE_SUCCESS = 'SUCCESS';
    final public const BILL_STATE_FAIL = 'FAIL';
    final public const BILL_STATE_CANCELING = 'CANCELING';
    final public const BILL_STATE_CANCELLED = 'CANCELLED';

    /**
     * From 2000 yuan on wechat demands the name of the recipient, encrypted with the platform
     * certificate. The platform stores amounts in fen, so the threshold is 200000.
     */
    private const USER_NAME_REQUIRED_AMOUNT = 200_000;

    /**
     * The limit of `transfer_remark`. The value could not be confirmed against the official reference
     * of the interface, so it is a guess - and it is guessed in the cheap direction: a remark that is
     * too long is cut here, while a request that is too long is refused by wechat and burns the out
     * number of the transfer.
     *
     * The cut is made on **bytes**, not on characters: a remark of 32 chinese characters is 96 bytes
     * and would pass a character count while still exceeding what wechat reads.
     */
    private const MAX_TRANSFER_REMARK_BYTES = 32;

    /**
     * The header naming the platform certificate whose public key encrypted a `user_name`.
     */
    private const HEADER_PLATFORM_SERIAL = 'Wechatpay-Serial';

    private const URI_TRANSFER = 'v3/fund-app/mch-transfer/transfer-bills';

    protected string $apiClass = WechatApi::class;

    public function __construct(private readonly PaymentCredentialCipher $cipher)
    {
    }

    /**
     * @throws PaymentException
     */
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
        $subject = $this->stringValue($model, PaymentGatewayModel::SUBJECT);

        $call = self::contentOf($request->transfer, $request->recipient, $request->channelParams, $this->cipher, $subject, $api);

        $this->recordRequest($model, [
            'uri' => self::URI_TRANSFER,
            'content' => $call['content'],
            'headers' => $call['headers'],
        ]);

        try {
            // the call of the interface is a rejection or a bill - an exception says the platform
            // never learned what became of the request, so it must not be booked as a failure
            $answer = $api->createTransfer($call['content'], $call['headers']);
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
     * The business content of the payout and the headers it needs, in the snake_case of the wire
     * format.
     *
     * The subject comes from the model, not from the entities - the model is the one source of the
     * payout the record and the wire content are built from, so they can not tell different stories.
     * Only the cut to the limit of the interface happens here, because that limit is wechat's, not a
     * rule of the platform.
     *
     * The amount is the amount of the transfer: wechat reads the field in fen, which is what the
     * platform stores, so nothing is converted.
     *
     * The name of the recipient is only encrypted from the threshold on, together with the header that
     * names the certificate. A header without the encrypted field and an encrypted field without the
     * header are both refused by the interface, so the two are decided in one place.
     *
     * @param array<string, mixed> $channelParams
     *
     * @throws PaymentException
     *
     * @return array{content: array<string, mixed>, headers: array<string, string>}
     */
    public static function contentOf(
        PaymentTransferEntity $transfer,
        PaymentTransferRecipientEntity $recipient,
        array $channelParams,
        PaymentCredentialCipher $cipher,
        string $subject,
        WechatApi $api,
    ): array {
        if ($recipient->identityType !== self::IDENTITY_TYPE_OPEN_ID) {
            throw PaymentException::transferIdentityNotSupported($transfer->provider, $recipient->identityType);
        }

        $sceneId = $channelParams[self::PARAM_TRANSFER_SCENE_ID] ?? null;

        if (!\is_string($sceneId) || trim($sceneId) === '') {
            // wechat refuses every call without the scene - a payout that can not be sent is not built
            throw PaymentException::missingParameter('channel_params.' . self::PARAM_TRANSFER_SCENE_ID);
        }

        // the identity is a credential at rest and is decrypted for this call alone
        $openId = $cipher->decryptValue($recipient->identityValue);

        if (trim($openId) === '') {
            throw PaymentException::transferRecipientIncomplete('identity_value');
        }

        $content = [
            'appid' => $api->appId(),
            // the platform number is validated as `[0-9a-zA-Z]{1,30}`, which stays under the 32
            // characters wechat allows on `out_bill_no` - no shortening is needed here
            'out_bill_no' => $transfer->outNo,
            'transfer_scene_id' => $sceneId,
            'openid' => $openId,
            // both sides count fen - a division here would move a hundredth of the money
            'transfer_amount' => $transfer->amount,
            'transfer_remark' => mb_strcut($subject !== '' ? $subject : PaymentGatewayModel::DEFAULT_SUBJECT, 0, self::MAX_TRANSFER_REMARK_BYTES),
        ];

        // the report infos ride along the way the caller filed them - wechat validates their shape and
        // decides whether the scene of this transfer needs them
        $reportInfos = $channelParams[self::PARAM_TRANSFER_SCENE_REPORT_INFOS] ?? null;

        if (\is_array($reportInfos) && $reportInfos !== []) {
            $content[self::PARAM_TRANSFER_SCENE_REPORT_INFOS] = $reportInfos;
        }

        $headers = [];

        if ($transfer->amount >= self::USER_NAME_REQUIRED_AMOUNT) {
            $content['user_name'] = self::encryptedUserNameOf($recipient, $api, $headers);
        }

        return ['content' => $content, 'headers' => $headers];
    }

    /**
     * The answer of the interface, narrowed to what a transfer means.
     *
     * Pure on purpose: the same answer arrives at every retry and every query, and what it means is
     * decided here and nowhere else.
     *
     * A `CANCELLED` bill is a `failed` transfer and not a `returned` one: the money never reached the
     * recipient, nothing came back. Only states that carry money back to the platform may be read as
     * `returned`, and wechat has none of those on this interface.
     *
     * @param array<string, mixed> $answer
     */
    public static function resultOf(array $answer): TransferResult
    {
        $state = self::stringOf($answer, 'state');
        $mapped = self::stateOf($state);

        return new TransferResult(
            $mapped,
            providerRef: self::providerRefOf($answer),
            confirmPayload: $mapped === TransferResult::STATE_AWAITING_CONFIRM ? self::confirmPayloadOf($answer) : null,
            failureCode: self::failureCodeOf($answer, $state),
        );
    }

    /**
     * The state of a bill, mapped to what the platform does with the transfer. The one place the
     * mapping lives - the submission and the query both read it.
     *
     * `WAIT_USER_CONFIRM` is the only state in which the shopper has to do something, and `CANCELLED`
     * is a failure: the money never arrived, so no `returned` may be booked from it.
     */
    public static function stateOf(string $state): string
    {
        return match ($state) {
            self::BILL_STATE_SUCCESS => TransferResult::STATE_SUCCEEDED,
            self::BILL_STATE_WAIT_USER_CONFIRM => TransferResult::STATE_AWAITING_CONFIRM,
            self::BILL_STATE_ACCEPTED, self::BILL_STATE_PROCESSING, self::BILL_STATE_TRANSFERING, self::BILL_STATE_CANCELING => TransferResult::STATE_PROCESSING,
            self::BILL_STATE_FAIL, self::BILL_STATE_CANCELLED => TransferResult::STATE_FAILED,
            default => TransferResult::STATE_UNKNOWN,
        };
    }

    /**
     * The failure code of a bill that will never arrive, from the reason the interface named when it
     * named one and from the state itself otherwise.
     *
     * @param array<string, mixed> $answer
     */
    public static function failureCodeOf(array $answer, string $state): ?string
    {
        return match ($state) {
            self::BILL_STATE_FAIL => self::stringOf($answer, 'fail_reason') ?: self::BILL_STATE_FAIL,
            self::BILL_STATE_CANCELLED => self::BILL_STATE_CANCELLED,
            default => null,
        };
    }

    /**
     * The bill number of the answer - the object every query of the transfer is addressed to.
     *
     * @param array<string, mixed> $answer
     */
    public static function providerRefOf(array $answer): ?string
    {
        return self::stringOf($answer, 'transfer_bill_no') ?: null;
    }

    /**
     * The `package_info` the shopper accepts the transfer with. It is single use and can not be
     * recovered, so it is passed on exactly as the interface sent it.
     *
     * @param array<string, mixed> $answer
     */
    public static function confirmPayloadOf(array $answer): ?string
    {
        return self::stringOf($answer, 'package_info') ?: null;
    }

    protected function api(): WechatApi
    {
        $api = parent::api();
        \assert($api instanceof WechatApi);

        return $api;
    }

    /**
     * The RSA encrypted name of the recipient, and the certificate serial in the headers of the very
     * request. Wechat decrypts the field with the certificate the header names, so the two can not be
     * built apart.
     *
     * @param array<string, string> $headers
     *
     * @throws PaymentException
     */
    private static function encryptedUserNameOf(
        PaymentTransferRecipientEntity $recipient,
        WechatApi $api,
        array &$headers,
    ): string {
        $name = $recipient->holderName ?? '';

        if (trim($name) === '') {
            // from 2000 yuan on the interface refuses the call without the name, and a refusal burns
            // the out number
            throw PaymentException::transferRecipientIncomplete('holder_name');
        }

        $certificate = $api->platformCertificate();

        $headers[self::HEADER_PLATFORM_SERIAL] = $certificate['serial'];

        return Rsa::encrypt($name, $api->publicKeyOf($certificate['certificate']));
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
