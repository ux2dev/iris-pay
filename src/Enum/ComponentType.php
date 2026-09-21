<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Enum;

enum ComponentType: string
{
    case Payment = 'payment';
    case PayWithIbanSelection = 'pay-with-iban-selection';
    case BudgetPayment = 'budget-payment';
    case PaymentData = 'payment-data';
    case PaymentDataWithAccountId = 'payment-data-with-accountid';
    case PayWithCode = 'pay-with-code';
    case AddIban = 'add-iban';
    case AddIbanWithBank = 'add-iban-with-bank';

    public function requiresHookHash(): bool
    {
        return match ($this) {
            self::Payment,
            self::PayWithIbanSelection,
            self::BudgetPayment,
            self::PaymentData,
            self::PaymentDataWithAccountId,
            self::PayWithCode => true,
            self::AddIban,
            self::AddIbanWithBank => false,
        };
    }
}
