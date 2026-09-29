<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Migration\Traits\ImportTranslationsTrait;
use Shopwell\Core\Migration\Traits\Translations;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1736824370MigrationMailTemplateForDocument extends MigrationStep
{
    use ImportTranslationsTrait;

    private const LOCALE_EN_GB = 'en-GB';
    private const LOCALE_ZH_CN = 'zh-CN';

    public function getCreationTimestamp(): int
    {
        return 1736824370;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $documentTypeTranslationMapping = [
            MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE,
            MailTemplateTypes::MAILTYPE_DOCUMENT_DELIVERY_NOTE,
            MailTemplateTypes::MAILTYPE_DOCUMENT_CREDIT_NOTE,
            MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE,
        ];

        $templateMapping = $this->getTemplateMapping();

        foreach ($documentTypeTranslationMapping as $technicalName) {
            $mailTemplateId = $connection->fetchOne('
                SELECT `mail_template`.`id`
                FROM `mail_template`
                INNER JOIN `mail_template_type`
                    ON `mail_template`.`mail_template_type_id` = `mail_template_type`.`id`
                    AND `mail_template_type`.`technical_name` = :technicalName
                WHERE `mail_template`.`updated_at` IS NULL
           ', ['technicalName' => $technicalName]);

            if (!$mailTemplateId) {
                continue;
            }

            $translations = new Translations(
                [
                    'mail_template_id' => $mailTemplateId,
                    'sender_name' => '{{ salesChannel.name }}',
                    'subject' => '您的订单有新文档',
                    'content_html' => $this->getMailTemplateContent($templateMapping, $technicalName, self::LOCALE_ZH_CN, true),
                    'content_plain' => $this->getMailTemplateContent($templateMapping, $technicalName, self::LOCALE_ZH_CN, false),
                ],
                [
                    'mail_template_id' => $mailTemplateId,
                    'sender_name' => '{{ salesChannel.name }}',
                    'subject' => 'New document for your order',
                    'content_html' => $this->getMailTemplateContent($templateMapping, $technicalName, self::LOCALE_EN_GB, true),
                    'content_plain' => $this->getMailTemplateContent($templateMapping, $technicalName, self::LOCALE_EN_GB, false),
                ],
            );

            $this->importTranslation('mail_template_translation', $translations, $connection);
        }
    }

    /**
     * @return array<string, array<string, array<string, string|false>>>
     */
    private function getTemplateMapping(): array
    {
        $filesystem = new Filesystem();

        $invoiceEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/invoice_mail/en-html.html.twig');
        $invoiceEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/invoice_mail/en-plain.html.twig');
        $invoiceDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/invoice_mail/zh-html.html.twig');
        $invoiceDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/invoice_mail/zh-plain.html.twig');
        $deliveryNoteEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/delivery_mail/en-html.html.twig');
        $deliveryNoteEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/delivery_mail/en-plain.html.twig');
        $deliveryNoteDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/delivery_mail/zh-html.html.twig');
        $deliveryNoteDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/delivery_mail/zh-plain.html.twig');
        $creditNoteEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/credit_note_mail/en-html.html.twig');
        $creditNoteEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/credit_note_mail/en-plain.html.twig');
        $creditNoteDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/credit_note_mail/zh-html.html.twig');
        $creditNoteDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/credit_note_mail/zh-plain.html.twig');
        $cancellationInvoiceEnHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/cancellation_mail/en-html.html.twig');
        $cancellationInvoiceEnPlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/cancellation_mail/en-plain.html.twig');
        $cancellationInvoiceDeHtml = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/cancellation_mail/zh-html.html.twig');
        $cancellationInvoiceDePlain = $filesystem->readFile(__DIR__ . '/../Fixtures/mails/cancellation_mail/zh-plain.html.twig');

        return [
            MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE => [
                self::LOCALE_EN_GB => [
                    'html' => $invoiceEnHtml,
                    'plain' => $invoiceEnPlain,
                ],
                self::LOCALE_ZH_CN => [
                    'html' => $invoiceDeHtml,
                    'plain' => $invoiceDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_DOCUMENT_DELIVERY_NOTE => [
                self::LOCALE_EN_GB => [
                    'html' => $deliveryNoteEnHtml,
                    'plain' => $deliveryNoteEnPlain,
                ],
                self::LOCALE_ZH_CN => [
                    'html' => $deliveryNoteDeHtml,
                    'plain' => $deliveryNoteDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_DOCUMENT_CREDIT_NOTE => [
                self::LOCALE_EN_GB => [
                    'html' => $creditNoteEnHtml,
                    'plain' => $creditNoteEnPlain,
                ],
                self::LOCALE_ZH_CN => [
                    'html' => $creditNoteDeHtml,
                    'plain' => $creditNoteDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE => [
                self::LOCALE_EN_GB => [
                    'html' => $cancellationInvoiceEnHtml,
                    'plain' => $cancellationInvoiceEnPlain,
                ],
                self::LOCALE_ZH_CN => [
                    'html' => $cancellationInvoiceDeHtml,
                    'plain' => $cancellationInvoiceDePlain,
                ],
            ],
        ];
    }

    /**
     * @param array<string, array<string, array<string, string|false>>> $templateMapping
     */
    private function getMailTemplateContent(array $templateMapping, string $technicalName, string $locale, bool $html): string
    {
        if (!\is_string($templateMapping[$technicalName][$locale][$html ? 'html' : 'plain'])) {
            return '';
        }

        return $templateMapping[$technicalName][$locale][$html ? 'html' : 'plain'];
    }
}
