<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MailTemplate\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class MailEventConfigurationException extends ShopwellHttpException
{
    public function __construct(
        string $message,
        string $eventClass
    ) {
        parent::__construct(
            'Failed processing the mail event: {{ errorMessage }}. {{ eventClass }}',
            [
                'errorMessage' => $message,
                'eventClass' => $eventClass,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MAIL_INVALID_EVENT_CONFIGURATION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
