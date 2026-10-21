<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Service;

use Shopwell\Core\Framework\Log\Package;

/**
 * The proof a guest carries between the verification and every later request.
 *
 * A guest has no session the shop could attach a return to, so the order number plus the mail address
 * are verified once and exchanged for a signed token. The token names the order and the address it
 * was verified with and is signed with the secret of the installation, which keeps it stateless: no
 * table grows with every guest who looks at a return, and a token can not be guessed into somebody
 * else's order.
 *
 * The token is deliberately short lived. A customer comes back to a return for a few days, not for a
 * year, and a leaked link should stop working on its own.
 *
 * @final
 */
#[Package('after-sales')]
class OrderReturnGuestTokenService
{
    private const TTL_SECONDS = 604800;

    /**
     * @internal
     */
    public function __construct(private readonly string $secret)
    {
    }

    public function issue(string $orderId, string $email): string
    {
        $payload = $this->encode([
            'o' => $orderId,
            'e' => mb_strtolower(trim($email)),
            'x' => time() + self::TTL_SECONDS,
        ]);

        return $payload . '.' . $this->sign($payload);
    }

    /**
     * @return array{orderId: string, email: string}|null null when the token is forged, broken or expired
     */
    public function resolve(?string $token): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }

        $parts = explode('.', $token);

        if (\count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;

        if (!hash_equals($this->sign($payload), $signature)) {
            return null;
        }

        $decoded = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);

        if (!\is_array($decoded) || !\is_string($decoded['o'] ?? null) || !\is_string($decoded['e'] ?? null)) {
            return null;
        }

        if (!\is_int($decoded['x'] ?? null) || $decoded['x'] < time()) {
            return null;
        }

        return ['orderId' => $decoded['o'], 'email' => $decoded['e']];
    }

    /**
     * What is stored on the return itself. The token is never stored, so a database dump does not
     * hand out access to the returns of every guest.
     */
    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function sign(string $payload): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $this->secret, true)), '+/', '-_'), '=');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data): string
    {
        return rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');
    }
}
