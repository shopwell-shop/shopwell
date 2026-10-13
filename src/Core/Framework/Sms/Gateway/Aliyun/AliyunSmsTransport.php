<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Gateway\Aliyun;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsResponseBody;
use Darabonba\OpenApi\Exceptions\AlibabaCloudException;
use Darabonba\OpenApi\Exceptions\ServerException;
use Darabonba\OpenApi\Exceptions\ThrottlingException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\Gateway\AbstractTemplateSmsTransport;
use Shopwell\Core\Framework\Sms\SmsException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Sends over the SendSms api of dysmsapi.
 *
 * The request and the version 3 signature are built by the official SDK, this transport only
 * translates a message of the component into a request and the answer back into what the pipeline
 * records. The rule to keep in mind is that aliyun answers with the code of the failure in the body
 * of an accepted request - only the body tells whether the message went out.
 *
 * @internal
 */
#[Package('framework')]
final class AliyunSmsTransport extends AbstractTemplateSmsTransport
{
    final public const TECHNICAL_NAME = 'aliyun';

    private const SUCCESS_CODE = 'OK';

    /**
     * Aliyun prefixes a failure at its own side with isp. and one the request caused with isv., and
     * only the first is worth another try: an isv. code names a template, a signature or a number
     * that has to be corrected at the console before the same request can succeed.
     *
     * The rate limit is the one exception - it says that nothing was sent and that the very same
     * request may go out later, so it is retried as well.
     *
     * @var list<string>
     */
    private const RETRYABLE_CODE_PREFIXES = [
        'isp.',
        'isv.BUSINESS_LIMIT_CONTROL',
    ];

    /**
     * @param Dysmsapi $sdk the client of the provider, the name does not collide with the http client
     *                      of the notifier that AbstractTransport keeps in $client
     */
    public function __construct(
        private readonly Dysmsapi $sdk,
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

        $request = new SendSmsRequest([
            // the api takes a domestic number without the country code and an international one
            // without the plus, so the e.164 form of the component is trimmed of it
            'phoneNumbers' => ltrim($this->recipient($message), '+'),
            'signName' => $data['signName'],
            'templateCode' => $data['templateCode'],
            'templateParam' => json_encode($data['params'], \JSON_THROW_ON_ERROR),
        ]);

        $options = $message->getOptions();

        if ($options instanceof AliyunSmsOptions) {
            $outId = $options->getOutId();

            if ($outId !== null && $outId !== '') {
                $request->outId = $outId;
            }
        }

        try {
            $response = $this->sdk->sendSms($request);
        } catch (ThrottlingException|ServerException $error) {
            // the sdk saw a status of the endpoint that asks for another try
            throw SmsException::providerUnavailable(self::TECHNICAL_NAME, $error->code, $error->message);
        } catch (AlibabaCloudException $error) {
            // everything else the endpoint refused, the code says whether it can pass later
            $this->fail($error->code, $error->message, self::RETRYABLE_CODE_PREFIXES);
        }

        $answer = self::answerOf($response->body);

        if ($answer['code'] !== self::SUCCESS_CODE) {
            $this->fail($answer['code'], $answer['message'], self::RETRYABLE_CODE_PREFIXES);
        }

        $sentMessage = new SentMessage($message, self::TECHNICAL_NAME, [
            'bizId' => $answer['bizId'],
            'requestId' => $answer['requestId'],
        ]);

        // the receipt of aliyun is the anchor for QuerySendDetails
        if ($answer['bizId'] !== null && $answer['bizId'] !== '') {
            $sentMessage->setMessageId($answer['bizId']);
        }

        return $sentMessage;
    }

    /**
     * The answer of aliyun as the component reads it.
     *
     * The model of the sdk declares the body and its fields as always set, while the sdk leaves every
     * one of them uninitialised when the endpoint answers without them. The nullability is declared
     * on the parameter, so the guarded reads stay in one place instead of spread over the send path.
     *
     * @return array{code: string, message: string, bizId: string|null, requestId: string|null}
     */
    private static function answerOf(?SendSmsResponseBody $body): array
    {
        return [
            // a coalesce reads an unset field with isset semantics, a plain read of it would warn
            'code' => $body->code ?? '',
            'message' => $body->message ?? '',
            'bizId' => $body?->bizId,
            'requestId' => $body?->requestId,
        ];
    }
}
