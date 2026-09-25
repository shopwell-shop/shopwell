<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Mail\Service;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;

/**
 * @phpstan-import-type MailData from AbstractMailFactory
 */
#[Package('after-sales')]
abstract class AbstractMailService
{
    abstract public function getDecorated(): AbstractMailService;

    /**
     * @param MailData $data
     * @param array<string, mixed> $templateData
     */
    abstract public function send(array $data, Context $context, array $templateData = []): ?Email;
}
