<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1591253089OrderDeeplinkForMailTemplates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1591253089;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $enLangId = $this->fetchLanguageId('en-GB', $connection);
        $zhCnLangId = $this->fetchLanguageId('zh-CN', $connection);

        $mailTemplateContent = require __DIR__ . '/../Fixtures/MailTemplateContent.php';

        // update order confirmation email templates
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['OrderConfirmation']['en-GB']['html'],
            $mailTemplateContent['OrderConfirmation']['en-GB']['plain'],
            $mailTemplateContent['OrderConfirmation']['zh-CN']['html'],
            $mailTemplateContent['OrderConfirmation']['zh-CN']['plain']
        );

        // update delivery email templates
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['DeliveryCancellation']['en-GB']['html'],
            $mailTemplateContent['DeliveryCancellation']['en-GB']['plain'],
            $mailTemplateContent['DeliveryCancellation']['zh-CN']['html'],
            $mailTemplateContent['DeliveryCancellation']['zh-CN']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['DeliveryReturned']['en-GB']['html'],
            $mailTemplateContent['DeliveryReturned']['en-GB']['plain'],
            $mailTemplateContent['DeliveryReturned']['zh-CN']['html'],
            $mailTemplateContent['DeliveryReturned']['zh-CN']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['DeliveryShippedPartially']['en-GB']['html'],
            $mailTemplateContent['DeliveryShippedPartially']['en-GB']['plain'],
            $mailTemplateContent['DeliveryShippedPartially']['zh-CN']['html'],
            $mailTemplateContent['DeliveryShippedPartially']['zh-CN']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['DeliveryShipped']['en-GB']['html'],
            $mailTemplateContent['DeliveryShipped']['en-GB']['plain'],
            $mailTemplateContent['DeliveryShipped']['zh-CN']['html'],
            $mailTemplateContent['DeliveryShipped']['zh-CN']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['DeliveryReturnedPartially']['en-GB']['html'],
            $mailTemplateContent['DeliveryReturnedPartially']['en-GB']['plain'],
            $mailTemplateContent['DeliveryReturnedPartially']['zh-CN']['html'],
            $mailTemplateContent['DeliveryReturnedPartially']['zh-CN']['plain']
        );

        // update order state email template
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['OrderCancelled']['en-GB']['html'],
            $mailTemplateContent['OrderCancelled']['en-GB']['plain'],
            $mailTemplateContent['OrderCancelled']['zh-CN']['html'],
            $mailTemplateContent['OrderCancelled']['zh-CN']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['OrderOpen']['en-GB']['html'],
            $mailTemplateContent['OrderOpen']['en-GB']['plain'],
            $mailTemplateContent['OrderOpen']['zh-CN']['html'],
            $mailTemplateContent['OrderOpen']['zh-CN']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['OrderInProgress']['en-GB']['html'],
            $mailTemplateContent['OrderInProgress']['en-GB']['plain'],
            $mailTemplateContent['OrderInProgress']['zh-CN']['html'],
            $mailTemplateContent['OrderInProgress']['zh-CN']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['OrderCompleted']['en-GB']['html'],
            $mailTemplateContent['OrderCompleted']['en-GB']['plain'],
            $mailTemplateContent['OrderCompleted']['zh-CN']['html'],
            $mailTemplateContent['OrderCompleted']['zh-CN']['plain']
        );

        // update payment email template
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['PaymentRefundedPartially']['en-GB']['html'],
            $mailTemplateContent['PaymentRefundedPartially']['en-GB']['plain'],
            $mailTemplateContent['PaymentRefundedPartially']['zh-CN']['html'],
            $mailTemplateContent['PaymentRefundedPartially']['zh-CN']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['PaymentReminded']['en-GB']['html'],
            $mailTemplateContent['PaymentReminded']['en-GB']['plain'],
            $mailTemplateContent['PaymentReminded']['zh-CN']['html'],
            $mailTemplateContent['PaymentReminded']['zh-CN']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['PaymentOpen']['en-GB']['html'],
            $mailTemplateContent['PaymentOpen']['en-GB']['plain'],
            $mailTemplateContent['PaymentOpen']['zh-CN']['html'],
            $mailTemplateContent['PaymentOpen']['zh-CN']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['PaymentPaid']['en-GB']['html'],
            $mailTemplateContent['PaymentPaid']['en-GB']['plain'],
            $mailTemplateContent['PaymentPaid']['zh-CN']['html'],
            $mailTemplateContent['PaymentPaid']['zh-CN']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['PaymentCancelled']['en-GB']['html'],
            $mailTemplateContent['PaymentCancelled']['en-GB']['plain'],
            $mailTemplateContent['PaymentCancelled']['zh-CN']['html'],
            $mailTemplateContent['PaymentCancelled']['zh-CN']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['PaymentRefunded']['en-GB']['html'],
            $mailTemplateContent['PaymentRefunded']['en-GB']['plain'],
            $mailTemplateContent['PaymentRefunded']['zh-CN']['html'],
            $mailTemplateContent['PaymentRefunded']['zh-CN']['plain']
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY,
            $connection,
            $enLangId,
            $zhCnLangId,
            $mailTemplateContent['PaymentPaidPartially']['en-GB']['html'],
            $mailTemplateContent['PaymentPaidPartially']['en-GB']['plain'],
            $mailTemplateContent['PaymentPaidPartially']['zh-CN']['html'],
            $mailTemplateContent['PaymentPaidPartially']['zh-CN']['plain']
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        $langId = $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => $code]);

        if (!$langId) {
            return null;
        }

        return $langId;
    }

    private function updateMailTemplate(
        string $mailTemplateType,
        Connection $connection,
        ?string $enLangId,
        ?string $zhCnLangId,
        string $getHtmlTemplateEn,
        string $getPlainTemplateEn,
        string $getHtmlTemplateZhCn,
        string $getPlainTemplateZhCn
    ): void {
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, $mailTemplateType);

        if ($templateId !== null) {
            // updating available entities of mail template
            $availableEntities = $this->fetchSystemMailTemplateAvailableEntitiesFromType($connection, $mailTemplateType);
            if (!isset($availableEntities['editOrderUrl'])) {
                $availableEntities['editOrderUrl'] = null;
                $sqlStatement = 'UPDATE `mail_template_type` SET `available_entities` = :availableEntities WHERE `technical_name` = :mailTemplateType AND `updated_at` IS NULL';
                $connection->executeStatement($sqlStatement, ['availableEntities' => json_encode($availableEntities, \JSON_THROW_ON_ERROR), 'mailTemplateType' => $mailTemplateType]);
            }

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $enLangId,
                $getHtmlTemplateEn,
                $getPlainTemplateEn
            );

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $zhCnLangId,
                $getHtmlTemplateZhCn,
                $getPlainTemplateZhCn
            );
        }
    }

    private function fetchSystemMailTemplateIdFromType(Connection $connection, string $mailTemplateType): ?string
    {
        $templateTypeId = $connection->executeQuery('
        SELECT `id` from `mail_template_type` WHERE `technical_name` = :type
        ', ['type' => $mailTemplateType])->fetchOne();

        $templateId = $connection->executeQuery('
        SELECT `id` from `mail_template` WHERE `mail_template_type_id` = :typeId AND `system_default` = 1 AND `updated_at` IS NULL
        ', ['typeId' => $templateTypeId])->fetchOne();

        if ($templateId === false || !\is_string($templateId)) {
            return null;
        }

        return $templateId;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSystemMailTemplateAvailableEntitiesFromType(Connection $connection, string $mailTemplateType): array
    {
        $availableEntities = $connection->executeQuery(
            'SELECT `available_entities` FROM `mail_template_type` WHERE `technical_name` = :mailTemplateType AND updated_at IS NULL;',
            ['mailTemplateType' => $mailTemplateType]
        )->fetchOne();

        if ($availableEntities === false || !\is_string($availableEntities) || json_decode($availableEntities, true, 512, \JSON_THROW_ON_ERROR) === null) {
            return [];
        }

        return json_decode($availableEntities, true, 512, \JSON_THROW_ON_ERROR);
    }

    private function updateMailTemplateTranslation(
        Connection $connection,
        string $mailTemplateId,
        ?string $langId,
        ?string $contentHtml,
        ?string $contentPlain,
        ?string $senderName = null
    ): void {
        if (!$langId) {
            return;
        }

        $sqlString = '';
        $sqlParams = [
            'templateId' => $mailTemplateId,
            'langId' => $langId,
        ];

        if ($contentHtml !== null) {
            $sqlString .= '`content_html` = :contentHtml ';
            $sqlParams['contentHtml'] = $contentHtml;
        }

        if ($contentPlain !== null) {
            $sqlString .= ($sqlString !== '' ? ', ' : '') . '`content_plain` = :contentPlain ';
            $sqlParams['contentPlain'] = $contentPlain;
        }

        if ($senderName !== null) {
            $sqlString .= ($sqlString !== '' ? ', ' : '') . '`sender_name` = :senderName ';
            $sqlParams['senderName'] = $senderName;
        }

        $sqlString = 'UPDATE `mail_template_translation` SET ' . $sqlString . 'WHERE `mail_template_id`= :templateId AND `language_id` = :langId AND `updated_at` IS NULL';

        $connection->executeStatement($sqlString, $sqlParams);
    }
}
