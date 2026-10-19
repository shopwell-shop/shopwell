<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;

/**
 * Adds the verification code template a shop can send without configuring anything first.
 *
 * The body is seeded like the one of a mail template: the Chinese text for every language of the zh
 * locale and the English text for every other one, and the system default language is always filled
 * so that no shop is left without a body to render. The template is seeded without a mapping - the
 * code and the signature of a reported template are what an account has to add for itself.
 *
 * @internal
 */
#[Package('framework')]
class Migration1792500001AddDefaultOtpSmsTemplate extends MigrationStep
{
    /**
     * The name the business code refers to, see SmsTemplateEntity.
     */
    private const TECHNICAL_NAME = 'customer.otp.login';

    private const CONTENT_ZH = '您的验证码是 {{ code }}，{{ minutes }} 分钟内有效，请勿泄露给他人。';

    private const CONTENT_EN = 'Your verification code is {{ code }}. It is valid for {{ minutes }} minutes.';

    /**
     * The placeholders the body refers to, checked before a provider is called.
     */
    private const PARAMS_SCHEMA = [
        ['name' => 'code', 'required' => true, 'type' => 'string', 'pattern' => '^\\d{6}$'],
        ['name' => 'minutes', 'required' => true, 'type' => 'string', 'pattern' => '^\\d+$'],
    ];

    /**
     * Two segments of a message, the upper bound the renderer of the free text mode enforces.
     */
    private const MAX_LENGTH = 140;

    private const VALIDITY_SECONDS = 300;

    public function getCreationTimestamp(): int
    {
        return 1792500001;
    }

    public function update(Connection $connection): void
    {
        $templateId = $this->templateId($connection) ?? $this->createTemplate($connection);

        $chineseLanguageIds = $this->languageIdsByLocalePrefix($connection, 'zh');

        // the system default language is always written, so its body is never inherited from an
        // empty one, and it is dropped again when it actually is a chinese language
        $englishLanguageIds = array_values(array_unique(array_diff(
            array_merge($this->languageIdsByLocalePrefix($connection, 'zh', true), [Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]),
            $chineseLanguageIds
        )));

        $this->addTranslations($connection, $templateId, $chineseLanguageIds, self::CONTENT_ZH);
        $this->addTranslations($connection, $templateId, $englishLanguageIds, self::CONTENT_EN);
    }

    private function createTemplate(Connection $connection): string
    {
        $templateId = Uuid::randomBytes();

        $connection->insert('sms_template', [
            'id' => $templateId,
            'technical_name' => self::TECHNICAL_NAME,
            'scene' => SmsTemplateEntity::SCENE_OTP,
            'params_schema' => \json_encode(self::PARAMS_SCHEMA, \JSON_THROW_ON_ERROR),
            'max_length' => self::MAX_LENGTH,
            'validity_seconds' => self::VALIDITY_SECONDS,
            'enabled' => true,
            'created_at' => $this->now(),
        ]);

        return $templateId;
    }

    /**
     * @param list<string> $languageIds
     */
    private function addTranslations(Connection $connection, string $templateId, array $languageIds, string $content): void
    {
        foreach ($languageIds as $languageId) {
            if ($this->hasTranslation($connection, $templateId, $languageId)) {
                continue;
            }

            $connection->insert('sms_template_translation', [
                'sms_template_id' => $templateId,
                'language_id' => $languageId,
                'content' => $content,
                'created_at' => $this->now(),
            ]);
        }
    }

    private function templateId(Connection $connection): ?string
    {
        $id = $connection->fetchOne(
            'SELECT `id` FROM `sms_template` WHERE `technical_name` = :technicalName',
            ['technicalName' => self::TECHNICAL_NAME]
        );

        return \is_string($id) ? $id : null;
    }

    private function hasTranslation(Connection $connection, string $templateId, string $languageId): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM `sms_template_translation` WHERE `sms_template_id` = :templateId AND `language_id` = :languageId',
            ['templateId' => $templateId, 'languageId' => $languageId]
        );
    }

    /**
     * The languages of a locale, the same way the mail templates pick the body to write.
     *
     * @return list<string>
     */
    private function languageIdsByLocalePrefix(Connection $connection, string $localePrefix, bool $invert = false): array
    {
        $languageIds = $connection->fetchFirstColumn(
            \sprintf(
                'SELECT `language`.`id`
                 FROM `language`
                 INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
                 WHERE LOWER(`locale`.`code`) %s :localePrefix
                 ORDER BY `language`.`created_at` ASC, `language`.`id` ASC',
                $invert ? 'NOT LIKE' : 'LIKE'
            ),
            ['localePrefix' => $localePrefix . '-%']
        );

        $result = [];

        foreach ($languageIds as $languageId) {
            if (!\is_string($languageId)) {
                continue;
            }

            $result[] = $languageId;
        }

        return $result;
    }

    private function now(): string
    {
        return (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
