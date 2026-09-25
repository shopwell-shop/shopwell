<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

use Shopwell\Tests\Integration\Core\Framework\Webhook\Service\WebhookHealthServiceTest;

/**
 * @codeCoverageIgnore
 *
 * @see WebhookHealthServiceTest
 */
class SeeExistingIntegrationTestClass
{
    public function describe(int $age): string
    {
        if ($age >= 18) {
            return 'adult';
        }

        return 'minor';
    }
}
