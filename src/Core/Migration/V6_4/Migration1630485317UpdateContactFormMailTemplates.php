<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Migration\Traits\MailUpdate;
use Shopwell\Core\Migration\Traits\UpdateMailTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1630485317UpdateContactFormMailTemplates extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1630485317;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $update = new MailUpdate(
            'contact_form',
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/contact_form/en-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/contact_form/en-html.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/contact_form/de-plain.html.twig'),
            $filesystem->readFile(__DIR__ . '/../Fixtures/mails/contact_form/de-html.html.twig')
        );

        $this->updateMail($update, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
