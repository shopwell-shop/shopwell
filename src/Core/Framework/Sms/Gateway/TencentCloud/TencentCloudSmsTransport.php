<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Gateway\TencentCloud;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\Gateway\AbstractTemplateSmsTransport;
use Shopwell\Core\Framework\Sms\SmsException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Sms\V20210111\Models\SendSmsRequest;
use TencentCloud\Sms\V20210111\Models\SendSmsResponse;
use TencentCloud\Sms\V20210111\Models\SendStatus;
use TencentCloud\Sms\V20210111\SmsClient;

/**
 * Sends over the SendSms api of tencent cloud.
 *
 * The request and the TC3 signature are built by the official SDK, this transport only translates a
 * message of the component into a request and the answer back into what the pipeline records.
 *
 * The failure of a request reaches this transport as an exception of the sdk: the sdk raises one for
 * every error the api reports in the body of an otherwise successful answer, so unlike aliyun there
 * is no second place the code of a refusal could be read from.
 *
 * @internal
 */
#[Package('framework')]
final class TencentCloudSmsTransport extends AbstractTemplateSmsTransport
{
    final public const TECHNICAL_NAME = 'tencentcloud';

    private const SUCCESS_CODE = 'Ok';

    /**
     * The codes tencent cloud documents as worth another try, an unlisted one is treated as final -
     * the provider refusing with a code it does not mark as transient will refuse just the same on
     * the next attempt.
     *
     * The last four entries are not api codes but the reason phrases the sdk uses as the code when
     * the api answered with a status other than 200, which the api itself never does - that is an
     * edge of tencent cloud and is worth another try.
     *
     * @var list<string>
     */
    private const RETRYABLE_CODE_PREFIXES = [
        'InternalError.',
        'RequestLimitExceeded',
        'FailedOperation.FailResolvePacket',
        'FailedOperation.JsonParseFail',
        'Internal Server Error',
        'Bad Gateway',
        'Service Unavailable',
        'Gateway Timeout',
    ];

    /**
     * @param SmsClient $sdk the client of the provider, the name does not collide with the http client
     *                       of the notifier that AbstractTransport keeps in $client
     */
    public function __construct(
        private readonly SmsClient $sdk,
        private readonly string $sdkAppId,
        private readonly ?string $defaultSignName,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($dispatcher);
    }

    protected function getTechnicalName(): string
    {
        return self::TECHNICAL_NAME;
    }

    protected function getDefaultSignName(): ?string
    {
        return $this->defaultSignName;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        $data = $this->templateData($message);

        $request = new SendSmsRequest();
        // the api takes the e.164 form including the plus, the same form the component works in
        $request->PhoneNumberSet = [$this->recipient($message)];
        $request->SignName = $data['signName'];
        $request->TemplateId = $data['templateCode'];
        $request->TemplateParamSet = $this->templateParams($data['params']);
        $request->SmsSdkAppId = $this->sdkAppId;

        $options = $message->getOptions();

        if ($options instanceof TencentCloudSmsOptions) {
            $sessionContext = $options->getSessionContext();

            if ($sessionContext !== null && $sessionContext !== '') {
                $request->SessionContext = $sessionContext;
            }
        }

        try {
            /** @var SendSmsResponse $response */
            $response = $this->sdk->SendSms($request);
        } catch (TencentCloudSDKException $error) {
            // a rejection of the api, or a request that never reached it
            $code = $error->getErrorCode();

            if ($code === '') {
                // the sdk wraps a failure of the transport itself without a code - the api has not
                // seen the message, so repeating it cannot send it twice
                throw SmsException::providerUnavailable(
                    self::TECHNICAL_NAME,
                    'TransportError',
                    $error->getMessage()
                );
            }

            $this->fail($code, $error->getMessage(), self::RETRYABLE_CODE_PREFIXES);
        }

        /** @var SendStatus|null $status */
        $status = $response->SendStatusSet[0] ?? null;

        if ($status === null) {
            throw SmsException::providerUnavailable(
                self::TECHNICAL_NAME,
                'MissingSendStatus',
                'The response of tencent cloud carried no status for the number.'
            );
        }

        $reported = self::statusOf($status);

        if ($reported['code'] !== self::SUCCESS_CODE) {
            $this->fail($reported['code'], $reported['message'], self::RETRYABLE_CODE_PREFIXES);
        }

        $sentMessage = new SentMessage($message, self::TECHNICAL_NAME, [
            'serialNo' => $reported['serialNo'],
            'requestId' => $response->RequestId,
        ]);

        // the serial number is the anchor for PullSmsSendStatus
        if ($reported['serialNo'] !== '') {
            $sentMessage->setMessageId($reported['serialNo']);
        }

        return $sentMessage;
    }

    /**
     * The state tencent cloud reports for the number.
     *
     * The model of the sdk declares the three fields as always set, while the sdk leaves them
     * uninitialised when the endpoint answers without them. The nullability is declared on the
     * parameter, so the guarded reads stay in one place instead of spread over the send path.
     *
     * @return array{code: string, message: string, serialNo: string}
     */
    private static function statusOf(?SendStatus $status): array
    {
        return [
            // a coalesce reads an unset field with isset semantics, a plain read of it would warn
            'code' => $status->Code ?? '',
            'message' => $status->Message ?? '',
            'serialNo' => $status->SerialNo ?? '',
        ];
    }

    /**
     * Tencent cloud knows the placeholders of a template by their number only, so the parameters are
     * submitted in the order of their names rather than as a map - a mapping names them 1, 2, 3.
     *
     * @param array<array-key, string> $params
     *
     * @return list<string>
     */
    private function templateParams(array $params): array
    {
        ksort($params);

        return array_values($params);
    }
}
