<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MailTemplate\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class MailTemplateRendererException extends ShopwellHttpException
{
    public function __construct(string $twigMessage)
    {
        parent::__construct(
            'Failed rendering mail template using Twig: {{ errorMessage }}',
            ['errorMessage' => $twigMessage]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MAIL_TEMPLATING_FAILED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
