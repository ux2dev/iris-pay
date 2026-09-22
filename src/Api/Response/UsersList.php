<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class UsersList
{
    /**
     * @param AgentUser[] $list
     */
    public function __construct(
        public int $pages,
        public int $size,
        public int $currPage,
        public array $list,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            pages: (int) $data['pages'],
            size: (int) $data['size'],
            currPage: (int) $data['currPage'],
            list: array_map(
                static fn (array $item): AgentUser => AgentUser::fromArray($item),
                $data['list'] ?? [],
            ),
        );
    }
}
