<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Policy;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalPolicyRegistry
{
    /**
     * @var array<string, ApprovalPolicyHandler>|null
     */
    private ?array $policies = null;

    /**
     * @param iterable<ApprovalPolicyHandler> $registeredPolicies
     */
    public function __construct(private readonly iterable $registeredPolicies)
    {
    }

    public function get(string $type, string $version): ApprovalPolicyHandler
    {
        if ($this->policies === null) {
            $this->policies = $this->load();
        }

        return $this->policies[$type . '@' . $version] ?? throw ApprovalFlowException::approvalPolicyNotFound($type, $version);
    }

    /**
     * @return array<string, ApprovalPolicyHandler>
     */
    private function load(): array
    {
        $policies = [];
        foreach ($this->registeredPolicies as $policy) {
            $type = $policy->getType();
            $version = $policy->getVersion();
            if (!preg_match('/^[a-z][a-z0-9_-]*:[a-z][a-zA-Z0-9_-]*$/D', $type)
                || !preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $version)) {
                throw ApprovalFlowException::invalidApprovalPolicy($policy::class);
            }
            $key = $type . '@' . $version;
            if (isset($policies[$key])) {
                throw ApprovalFlowException::duplicateApprovalPolicy($type, $version);
            }
            $policies[$key] = $policy;
        }

        return $policies;
    }
}
