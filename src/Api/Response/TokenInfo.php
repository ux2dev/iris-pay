<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Api\Enum\TokenType;

final readonly class TokenInfo
{
    public function __construct(
        public int $id,
        public int $accountId,
        public string $token,
        public TokenType $type,
        public int $bankId,
        public int $psuId,
        public string $psuIdentifier,
        public string $dateCreated,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            accountId: (int) $data['accountId'],
            token: $data['token'],
            type: TokenType::from($data['type']),
            bankId: (int) $data['bankId'],
            psuId: (int) $data['psuId'],
            psuIdentifier: $data['psuIdentifier'],
            dateCreated: $data['dateCreated'],
        );
    }
}
