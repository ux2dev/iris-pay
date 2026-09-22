<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Enum;

enum Environment: string
{
    case Development = 'development';
    case Production = 'production';

    public function payByLinkBaseUrl(): string
    {
        return match ($this) {
            self::Development => 'https://dev.paybyclick.irispay.bg',
            self::Production => 'https://paybyclick.irispay.bg',
        };
    }

    public function webSdkBaseUrl(): string
    {
        return match ($this) {
            self::Development => 'https://developer.sandbox.irispay.bg',
            self::Production => 'https://developer.irispay.bg',
        };
    }

    public function webSdkAssetsUrl(): string
    {
        return match ($this) {
            self::Development => 'https://websdk.sandbox.irispay.bg',
            self::Production => 'https://websdk.irispay.bg',
        };
    }
}
