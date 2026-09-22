<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

use Ux2Dev\Iris\Api\Enum\OptionalHookEvent;

final readonly class CreateHookData
{
    /**
     * @param OptionalHookEvent[] $optionalEvents
     */
    public function __construct(
        public string $url,
        public string $agentHash,
        public ?string $state = null,
        public array $optionalEvents = [],
        public ?string $successUrl = null,
        public ?string $errorUrl = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'url' => $this->url,
            'agentHash' => $this->agentHash,
            'optionalEvents' => array_map(
                static fn (OptionalHookEvent $event): string => $event->value,
                $this->optionalEvents,
            ),
        ];

        if ($this->state !== null) {
            $data['state'] = $this->state;
        }

        if ($this->successUrl !== null) {
            $data['successUrl'] = $this->successUrl;
        }

        if ($this->errorUrl !== null) {
            $data['errorUrl'] = $this->errorUrl;
        }

        return $data;
    }
}
