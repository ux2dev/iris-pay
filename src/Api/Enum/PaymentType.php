<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum PaymentType: string
{
    case DomesticCreditTransfer = 'DOMESTIC_CREDIT_TRANSFER';
    case DomesticBudgetTransfer = 'DOMESTIC_BUDGET_TRANSFER';
    case SepaCreditTransfer = 'SEPA_CREDIT_TRANSFER';
    case CrossBorderTransfer = 'CROSS_BORDER_TRANSFER';
    case BulkDomesticCreditTransfer = 'BULK_DOMESTIC_CREDIT_TRANSFER';
    case BulkDomesticBudgetTransfer = 'BULK_DOMESTIC_BUDGET_TRANSFER';
    case BulkSepaCreditTransfer = 'BULK_SEPA_CREDIT_TRANSFER';
    case BulkEntry = 'BULK_ENTRY';
    case SepaBudgetTransfer = 'SEPA_BUDGET_TRANSFER';
    case BulkSepaBudgetTransfer = 'BULK_SEPA_BUDGET_TRANSFER';
    case Domestic = 'DOMESTIC';
    case Budget = 'BUDGET';
    case Sepa = 'SEPA';
    case CrossBorder = 'CROSS_BORDER';
    case BulkDomestic = 'BULK_DOMESTIC';
    case BulkSepa = 'BULK_SEPA';
    case BulkBudget = 'BULK_BUDGET';
    case InstantDomestic = 'INSTANT_DOMESTIC';
}
