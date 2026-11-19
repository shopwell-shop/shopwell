<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Graph\Codec;

use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphViolation;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\ViolationCategory;
use Shopwell\Core\Framework\Log\Package;

/**
 * Native JSON decoding owns syntax and depth limits.
 * A second pass over its valid tokens rejects duplicate keys and numeric rounding before values cross the boundary.
 *
 * @internal
 */
#[Package('framework')]
class GraphJsonDecoder
{
    private const MAX_SAFE_INTEGER = 9007199254740991;

    public function decode(string $json): mixed
    {
        try {
            $decoded = json_decode($json, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw GraphJsonException::forViolation(new GraphViolation(ViolationCategory::Structure, 'invalid_json', '', 'Document is not valid JSON.'), $exception);
        }

        $matched = preg_match_all('/"(?:[^"\\\\]++|\\\\.)*+"|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?|true|false|null|[{}\[\]:,]/s', $json, $matches);
        if ($matched === false) {
            throw GraphJsonException::forViolation(new GraphViolation(ViolationCategory::Structure, 'json_tokenization_failed', '', 'JSON tokenization exceeded its resource limit.'));
        }
        $index = 0;
        $this->inspectValue($matches[0], $index, '');

        return $decoded;
    }

    /**
     * @param list<string> $tokens
     */
    private function inspectValue(array $tokens, int &$index, string $path): void
    {
        $token = $tokens[$index++];
        if ($token === '{') {
            $keys = [];
            while ($tokens[$index] !== '}') {
                $key = json_decode($tokens[$index++], flags: \JSON_THROW_ON_ERROR);
                if (!\is_string($key)) {
                    throw GraphJsonException::forViolation(new GraphViolation(ViolationCategory::Structure, 'invalid_json', $path, 'Object key could not be decoded.'));
                }
                $keyPath = $path . '/' . str_replace(['~', '/'], ['~0', '~1'], $key);
                if (isset($keys[$key])) {
                    throw GraphJsonException::forViolation(new GraphViolation(ViolationCategory::Structure, 'duplicate_json_key', $keyPath, 'Duplicate JSON object key.'));
                }
                $keys[$key] = true;
                ++$index; // The native parser has already established the colon and delimiters.
                $this->inspectValue($tokens, $index, $keyPath);
                if ($tokens[$index] === ',') {
                    ++$index;
                }
            }
            ++$index;

            return;
        }
        if ($token === '[') {
            $item = 0;
            while ($tokens[$index] !== ']') {
                $this->inspectValue($tokens, $index, $path . '/' . $item++);
                if ($tokens[$index] === ',') {
                    ++$index;
                }
            }
            ++$index;

            return;
        }
        if ($token[0] === '-' || ctype_digit($token[0])) {
            $this->inspectNumber($token, $path);
        }
    }

    private function inspectNumber(string $token, string $path): void
    {
        $number = json_decode($token, flags: \JSON_THROW_ON_ERROR);
        if (!\is_int($number) && !\is_float($number)) {
            throw GraphJsonException::forViolation(new GraphViolation(ViolationCategory::Structure, 'invalid_json', $path, 'Number token could not be decoded.'));
        }
        if (!is_finite($number) || abs($number) > self::MAX_SAFE_INTEGER) {
            throw GraphJsonException::forViolation(new GraphViolation(ViolationCategory::Structure, 'unsafe_number', $path, 'JSON numbers must be finite and within the JavaScript safe integer range; use a decimal string for larger values.'));
        }
        $encoded = json_encode($number, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
        if ($this->decimalIdentity($token) !== $this->decimalIdentity($encoded)) {
            throw GraphJsonException::forViolation(new GraphViolation(ViolationCategory::Structure, 'unsafe_number', $path, 'JSON number would lose precision when encoded; use a decimal string.'));
        }
    }

    private function decimalIdentity(string $token): string
    {
        if (preg_match('/^(-?)([0-9]+)(?:\.([0-9]+))?(?:[eE]([+-]?[0-9]+))?$/D', $token, $parts) !== 1) {
            throw GraphJsonException::forViolation(new GraphViolation(ViolationCategory::Structure, 'invalid_json', '', 'Number token could not be normalized.'));
        }
        $fraction = $parts[3] ?? '';
        $digits = ltrim($parts[2] . $fraction, '0');
        if ($digits === '') {
            return '0';
        }
        $coefficient = rtrim($digits, '0');
        $exponent = $parts[4] ?? '0';
        // A nonzero finite double cannot have an exponent this long; keep conversion bounded for hostile input.
        if (\strlen(ltrim($exponent, '+-0')) > 5) {
            return 'outside-double-range';
        }
        $power = (int) $exponent - \strlen($fraction) + \strlen($digits) - \strlen($coefficient);

        return $parts[1] . $coefficient . 'e' . $power;
    }
}
