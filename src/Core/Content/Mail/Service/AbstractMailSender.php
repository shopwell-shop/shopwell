<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Mail\Service;

use Shopwell\Core\Content\Mail\MailException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;

#[Package('after-sales')]
abstract class AbstractMailSender
{
    abstract public function getDecorated(): AbstractMailSender;

    /**
     * @throws MailException
     */
    abstract public function send(Email $email): void;
}
