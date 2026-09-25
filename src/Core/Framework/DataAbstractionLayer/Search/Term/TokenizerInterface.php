<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Search\Term;

use Shopwell\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
interface TokenizerInterface
{
    /**
     * @return list<string>
     */
    #[NewOptionalParameter(version: 'v6.8.0', parameterName: 'tokenMinimumLength', parameterType: '?int', defaultValue: null)]
    public function tokenize(string $string/* , ?int $tokenMinimumLength = null */): array;
}
