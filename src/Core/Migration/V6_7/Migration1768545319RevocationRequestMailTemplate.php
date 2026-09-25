<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Migration\Structs\MailTemplateCreateStruct;
use Shopwell\Core\Migration\Structs\MailTemplateTypeCreateStruct;
use Shopwell\Core\Migration\Traits\CreateMailTemplateTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1768545319RevocationRequestMailTemplate extends MigrationStep
{
    use CreateMailTemplateTrait;

    public const MERCHANT_DIRECTORY = 'revocation_request.merchant';
    public const CUSTOMER_DIRECTORY = 'revocation_request.customer';

    public function getCreationTimestamp(): int
    {
        return 1768545319;
    }

    public function update(Connection $connection): void
    {
        $merchantTypeStruct = new MailTemplateTypeCreateStruct(
            MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_MERCHANT,
            'Revocation request received',
            'Widerrufsantrag erhalten',
        );

        $merchantTemplate = new MailTemplateCreateStruct(
            self::MERCHANT_DIRECTORY,
            'Revocation request received',
            'Widerrufsantrag erhalten',
            'Received revocation request from customer',
            'Widerrufsantrag vom Kunden erhalten',
            '{{ salesChannel.translated.name }}',
            '{{ salesChannel.translated.name }}',
        );

        $this->createMail($connection, $merchantTypeStruct, $merchantTemplate);

        $customerType = new MailTemplateTypeCreateStruct(
            MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_CUSTOMER,
            'Revocation request requested',
            'Widerrufsantrag gestellt',
        );

        $customerTemplate = new MailTemplateCreateStruct(
            self::CUSTOMER_DIRECTORY,
            'Revocation request sent',
            'Widerrufsantrag gesendet',
            'Confirmation receipt of customers revocation request',
            'Empfangsbestätigung für Widerrufsantrag des Kunden',
            '{{ salesChannel.translated.name }}',
            '{{ salesChannel.translated.name }}',
        );

        $this->createMail($connection, $customerType, $customerTemplate);
    }
}
