<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Migration\Traits\MailUpdate;
use Shopwell\Core\Migration\Traits\UpdateMailTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1701688920FixDownloadLinkMail extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1701688920;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $update = new MailUpdate(
            MailTemplateTypes::MAILTYPE_DOWNLOADS_DELIVERY,
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/downloads_delivery/en-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/downloads_delivery/en-html.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/downloads_delivery/de-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/downloads_delivery/de-html.html.twig')
        );

        $this->updateMail($update, $connection);
    }
}
