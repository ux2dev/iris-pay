<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\WebSdk;

use Ux2Dev\Iris\Enum\ComponentType;
use Ux2Dev\Iris\Enum\Country;
use Ux2Dev\Iris\Enum\Environment;
use Ux2Dev\Iris\Enum\Language;

final readonly class ComponentConfig
{
    /**
     * @param array<string, mixed>|null $paymentData       For payment-data type
     * @param array<string, mixed>|null $paginationOptions Pagination config
     * @param array<string, mixed>|null $headerOptions     Header config
     */
    public function __construct(
        public ComponentType $type,
        public string $userHash,
        public Environment $backend,
        public ?string $hookHash = null,
        public ?string $ibanHookHash = null,
        public ?Language $lang = null,
        public ?Country $country = null,
        public ?bool $showBankSelector = null,
        public ?string $bankHash = null,
        public ?string $useOnlySelectedBankHashes = null,
        public ?string $redirectUrl = null,
        public ?int $redirectTimeout = null,
        public ?string $code = null,
        public ?array $paymentData = null,
        public ?array $paymentDataWithAccountId = null,
        public ?array $paginationOptions = null,
        public ?array $headerOptions = null,
    ) {}

    /** @return array<string, string> */
    public function toAttributes(): array
    {
        $attrs = [
            'type' => $this->type->value,
            'userhash' => $this->userHash,
            'backend' => $this->backend->webSdkBaseUrl(),
        ];

        if ($this->hookHash !== null) {
            $attrs['hookhash'] = $this->hookHash;
        }
        if ($this->ibanHookHash !== null) {
            $attrs['ibanhookhash'] = $this->ibanHookHash;
        }
        if ($this->lang !== null) {
            $attrs['lang'] = $this->lang->value;
        }
        if ($this->country !== null) {
            $attrs['country'] = $this->country->value;
        }
        if ($this->showBankSelector !== null) {
            $attrs['show_bank_selector'] = $this->showBankSelector ? 'true' : 'false';
        }
        if ($this->bankHash !== null) {
            $attrs['bankhash'] = $this->bankHash;
        }
        if ($this->useOnlySelectedBankHashes !== null) {
            $attrs['useOnlySelectedBankHashes'] = $this->useOnlySelectedBankHashes;
        }
        if ($this->redirectUrl !== null) {
            $attrs['redirect_url'] = $this->redirectUrl;
        }
        if ($this->redirectTimeout !== null) {
            $attrs['redirect_timeout'] = (string) $this->redirectTimeout;
        }
        if ($this->code !== null) {
            $attrs['code'] = $this->code;
        }
        if ($this->paymentData !== null) {
            $attrs['payment_data'] = json_encode($this->paymentData);
            $attrs['show_bank_selector'] = $attrs['show_bank_selector'] ?? 'false';
        }
        if ($this->paymentDataWithAccountId !== null) {
            $attrs['payment_data_with_account_id'] = json_encode($this->paymentDataWithAccountId);
            $attrs['show_bank_selector'] = $attrs['show_bank_selector'] ?? 'false';
        }
        if ($this->paginationOptions !== null) {
            $attrs['pagination_options'] = json_encode($this->paginationOptions);
        }
        if ($this->headerOptions !== null) {
            $attrs['header_options'] = json_encode($this->headerOptions);
        }

        return $attrs;
    }

    public static function assetTags(Environment $environment): string
    {
        $base = $environment->webSdkAssetsUrl();

        return <<<HTML
        <script src="{$base}/assets/irispay-ui/elements.js"></script>
        <link rel="stylesheet" href="{$base}/assets/irispay-ui/styles.css">
        HTML;
    }
}
