<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\SystemCheck;

use Shopwell\Core\Framework\App\Url\AppUrlVerifier;
use Shopwell\Core\Framework\App\Url\VerificationStatus;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\SystemCheck\BaseCheck;
use Shopwell\Core\Framework\SystemCheck\Check\Category;
use Shopwell\Core\Framework\SystemCheck\Check\Result;
use Shopwell\Core\Framework\SystemCheck\Check\Status;
use Shopwell\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;

/**
 * @internal
 */
#[Package('framework')]
class AppUrlVerificationCheck extends BaseCheck
{
    public function __construct(private readonly AppUrlVerifier $appUrlVerifier)
    {
    }

    public function run(): Result
    {
        $state = $this->appUrlVerifier->forceVerify();

        $extra = [
            'appUrl' => $state->appUrl,
            'info' => $state->info,
            'statusCode' => $state->statusCode,
        ];

        return match ($state->status) {
            VerificationStatus::PASS => new Result($this->name(), Status::OK, 'APP_URL points to this shop', true, $extra),
            VerificationStatus::SOFT_FAIL => new Result($this->name(), Status::WARNING, 'APP_URL could not be verified, this is usually temporary', false, $extra),
            VerificationStatus::HARD_FAIL => new Result($this->name(), Status::ERROR, 'APP_URL does not point to this shop or is blocked, apps cannot be installed', false, $extra),
        };
    }

    public function category(): Category
    {
        return Category::EXTERNAL;
    }

    public function name(): string
    {
        return 'AppUrlVerification';
    }

    /**
     * Not part of the web and pre-rollout checks: verifying sends a request to APP_URL,
     * and before a rollout APP_URL may still point to the old installation.
     */
    protected function allowedSystemCheckExecutionContexts(): array
    {
        return [SystemCheckExecutionContext::CLI, SystemCheckExecutionContext::RECURRENT];
    }
}
