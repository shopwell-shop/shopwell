<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\Traits\MailUpdate;
use Shopwell\Core\Migration\Traits\UpdateMailTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1688106315AddMissingTransactionMailTemplates extends MigrationStep
{
    use UpdateMailTrait;

    public const AUTHORIZED_TYPE = 'order_transaction.state.authorized';

    public const CHARGEBACK_TYPE = 'order_transaction.state.chargeback';

    public const UNCONFIRMED_TYPE = 'order_transaction.state.unconfirmed';

    private const ZH_CN_LANGUAGE_NAME = '简体中文';

    private const ENGLISH_LANGUAGE_NAME = 'English';

    public function getCreationTimestamp(): int
    {
        return 1688106315;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $mails = [
            self::AUTHORIZED_TYPE => [
                'type' => [
                    'technicalName' => self::AUTHORIZED_TYPE,
                    'availableEntities' => '{"order":"order","previousState":"state_machine_state","newState":"state_machine_state","salesChannel":"sales_channel","editOrderUrl":null}',
                ],
                'template' => [
                    'htmlZh' => $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/zh-html.html.twig'),
                    'plainZh' => $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/zh-plain.html.twig'),
                    'htmlEn' => $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/en-html.html.twig'),
                    'plainEn' => $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/en-plain.html.twig'),
                ],
                'translations' => [
                    'en' => [
                        'name' => 'Enter payment state: Authorized',
                        'subject' => 'The order at {{ salesChannel.name }} was authorized',
                        'description' => 'Shopwell Default Template',
                    ],
                    'zh' => [
                        'name' => '进入支付状态：已授权',
                        'subject' => '您在 {{ salesChannel.name }} 的订单已授权',
                        'description' => 'Shopwell 基础模板',
                    ],
                ],
            ],
            self::CHARGEBACK_TYPE => [
                'type' => [
                    'technicalName' => self::CHARGEBACK_TYPE,
                    'availableEntities' => '{"order":"order","previousState":"state_machine_state","newState":"state_machine_state","salesChannel":"sales_channel","editOrderUrl":null}',
                ],
                'template' => [
                    'htmlZh' => $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/zh-html.html.twig'),
                    'plainZh' => $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/zh-plain.html.twig'),
                    'htmlEn' => $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/en-html.html.twig'),
                    'plainEn' => $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/en-plain.html.twig'),
                ],
                'translations' => [
                    'en' => [
                        'name' => 'Enter payment state: Chargeback',
                        'subject' => 'Chargeback for your order with {{ salesChannel.name }}',
                        'description' => 'Shopwell Default Template',
                    ],
                    'zh' => [
                        'name' => '进入支付状态：拒付',
                        'subject' => '您在 {{ salesChannel.name }} 的订单发生拒付',
                        'description' => 'Shopwell 基础模板',
                    ],
                ],
            ],
            self::UNCONFIRMED_TYPE => [
                'type' => [
                    'technicalName' => self::UNCONFIRMED_TYPE,
                    'availableEntities' => '{"order":"order","previousState":"state_machine_state","newState":"state_machine_state","salesChannel":"sales_channel","editOrderUrl":null}',
                ],
                'template' => [
                    'htmlZh' => $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/zh-html.html.twig'),
                    'plainZh' => $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/zh-plain.html.twig'),
                    'htmlEn' => $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/en-html.html.twig'),
                    'plainEn' => $filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/en-plain.html.twig'),
                ],
                'translations' => [
                    'en' => [
                        'name' => 'Enter payment state: Unconfirmed',
                        'subject' => 'Your order with {{ salesChannel.name }} is unconfirmed',
                        'description' => 'Shopwell Default Template',
                    ],
                    'zh' => [
                        'name' => '您在 {{ salesChannel.name }} 的订单未确认',
                        'subject' => '',
                        'description' => 'Shopwell 基础模板',
                    ],
                ],
            ],
        ];

        foreach ($mails as $mail) {
            $typeName = $mail['type']['technicalName'];

            $templateTypeId = $this->insertMailTemplateTypeData($typeName, $mail, $connection);
            $this->insertMailTemplateData($templateTypeId, $mail, $connection);
            $this->updateMailTemplateContent($typeName, $mail, $connection);
        }
    }

    private function fetchLanguageIdByName(string $name, Connection $connection): ?string
    {
        try {
            $result = $connection->fetchOne(
                'SELECT id FROM `language` WHERE `name` = :languageName',
                ['languageName' => $name]
            );

            if (!\is_string($result)) {
                return null;
            }

            return $result;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $mail
     *
     * @throws Exception
     */
    private function insertMailTemplateTypeData(string $typeName, array $mail, Connection $connection): string
    {
        $templateTypeId = $connection->fetchOne('SELECT id FROM mail_template_type WHERE technical_name = :name', ['name' => $typeName]);

        if ($templateTypeId) {
            return \is_string($templateTypeId) ? $templateTypeId : '';
        }

        $templateTypeId = Uuid::randomBytes();
        $connection->insert(
            'mail_template_type',
            [
                'id' => $templateTypeId,
                'technical_name' => $typeName,
                'available_entities' => $mail['type']['availableEntities'],
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $englishLanguageId = $this->fetchLanguageIdByName(self::ENGLISH_LANGUAGE_NAME, $connection);
        $zhCnLanguageId = $this->fetchLanguageIdByName(self::ZH_CN_LANGUAGE_NAME, $connection);

        if (!\in_array($defaultLanguageId, [$englishLanguageId, $zhCnLanguageId], true)) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $templateTypeId,
                    'language_id' => $defaultLanguageId,
                    'name' => $mail['translations']['en']['name'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($englishLanguageId) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $templateTypeId,
                    'language_id' => $englishLanguageId,
                    'name' => $mail['translations']['en']['name'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($zhCnLanguageId) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $templateTypeId,
                    'language_id' => $zhCnLanguageId,
                    'name' => $mail['translations']['zh']['name'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        return $templateTypeId;
    }

    /**
     * @param array<string, mixed> $mail
     *
     * @throws Exception
     */
    private function insertMailTemplateData(string $templateTypeId, array $mail, Connection $connection): void
    {
        $templateId = Uuid::randomBytes();
        $connection->insert(
            'mail_template',
            [
                'id' => $templateId,
                'mail_template_type_id' => $templateTypeId,
                'system_default' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $englishLanguageId = $this->fetchLanguageIdByName(self::ENGLISH_LANGUAGE_NAME, $connection);
        $zhCnLanguageId = $this->fetchLanguageIdByName(self::ZH_CN_LANGUAGE_NAME, $connection);

        if (!\in_array($defaultLanguageId, [$englishLanguageId, $zhCnLanguageId], true)) {
            $connection->insert(
                'mail_template_translation',
                [
                    'subject' => $mail['translations']['en']['subject'],
                    'description' => $mail['translations']['en']['description'],
                    'sender_name' => '{{ salesChannel.name }}',
                    'content_html' => '',
                    'content_plain' => '',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'mail_template_id' => $templateId,
                    'language_id' => $defaultLanguageId,
                ]
            );
        }

        if ($englishLanguageId) {
            $connection->insert(
                'mail_template_translation',
                [
                    'subject' => $mail['translations']['en']['subject'],
                    'description' => $mail['translations']['en']['description'],
                    'sender_name' => '{{ salesChannel.name }}',
                    'content_html' => '',
                    'content_plain' => '',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'mail_template_id' => $templateId,
                    'language_id' => $englishLanguageId,
                ]
            );
        }

        if ($zhCnLanguageId) {
            $connection->insert(
                'mail_template_translation',
                [
                    'subject' => $mail['translations']['zh']['subject'],
                    'description' => $mail['translations']['zh']['description'],
                    'sender_name' => '{{ salesChannel.name }}',
                    'content_html' => '',
                    'content_plain' => '',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'mail_template_id' => $templateId,
                    'language_id' => $zhCnLanguageId,
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $mail
     */
    private function updateMailTemplateContent(string $typeName, array $mail, Connection $connection): void
    {
        $update = new MailUpdate(
            $typeName,
            $mail['template']['plainEn'],
            $mail['template']['htmlEn'],
            $mail['template']['plainZh'],
            $mail['template']['htmlEn'],
        );

        $this->updateMail($update, $connection);
    }
}
