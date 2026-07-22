<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Enums;

enum PaymentState: string
{
    case WalletNotStarted = 'wallet_not_started';
    case WalletPending = 'wallet_pending';
    case WalletFailed = 'wallet_failed';
    case WalletSuccess = 'wallet_success';
    case WalletNotRequired = 'wallet_not_required';
    case BeneficiaryPaymentPending = 'beneficiary_payment_pending';
    case BeneficiaryPaymentSubmitted = 'beneficiary_payment_submitted';
    case BeneficiaryPaymentFailed = 'beneficiary_payment_failed';
    case BeneficiaryPaymentSuccess = 'beneficiary_payment_success';
}
