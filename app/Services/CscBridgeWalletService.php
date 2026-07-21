<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\WalletServiceInterface;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipWalletTransaction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CscBridgeWalletService implements WalletServiceInterface
{
    public function initiateApplicationFee(ScholarshipApplication $application, User $user): ScholarshipWalletTransaction
    {
        $existing = ScholarshipWalletTransaction::query()
            ->where('scholarship_application_id', $application->id)
            ->where('transaction_type', 'application_fee')
            ->whereIn('status', ['pending', 'posted'])
            ->first();

        if ($existing instanceof ScholarshipWalletTransaction) {
            return $existing;
        }

        $merchantTxn = 'FDGC'.$application->id.time();
        $receipt = 'FDGC#'.$application->id.time();
        $amount = (float) config('csc.bridge.application_fee');
        $payload = [
            'csc_id' => $user->csc_id ?? $user->id,
            'merchant_id' => config('csc.bridge.merchant_id'),
            'merchant_receipt_no' => $receipt,
            'txn_amount' => $amount,
            'return_url' => route('applications.wallet.callback', $application),
            'cancel_url' => route('applications.wallet.callback', $application),
            'product_id' => config('csc.bridge.product_id'),
            'product_name' => config('csc.bridge.product_name'),
            'merchant_txn' => $merchantTxn,
            'param_1' => 'New Scholarship Application',
        ];

        return ScholarshipWalletTransaction::query()->create([
            'scholarship_application_id' => $application->id,
            'user_id' => $user->id,
            'transaction_type' => 'application_fee',
            'amount' => $amount,
            'reference' => $merchantTxn,
            'status' => 'pending',
            'metadata' => [
                'merchant_receipt' => $receipt,
                'request' => $payload,
                'gateway_url' => rtrim((string) config('csc.bridge.wallet_payment_url'), '/').'/'.$this->fraction(),
            ],
        ]);
    }

    public function completeApplicationFee(ScholarshipApplication $application, array $response, User $user): ScholarshipWalletTransaction
    {
        $transaction = $this->transactionForResponse($application, $response);

        if ($transaction->status === 'posted') {
            return $transaction;
        }

        $transaction->fill([
            'status' => 'posted',
            'metadata' => array_merge($transaction->metadata ?? [], ['response' => $response]),
        ])->save();

        return $transaction->refresh();
    }

    public function failApplicationFee(ScholarshipApplication $application, array $response, User $user): ScholarshipWalletTransaction
    {
        $transaction = $this->transactionForResponse($application, $response);
        $transaction->fill([
            'status' => 'failed',
            'metadata' => array_merge($transaction->metadata ?? [], ['response' => $response]),
        ])->save();

        return $transaction->refresh();
    }

    private function transactionForResponse(ScholarshipApplication $application, array $response): ScholarshipWalletTransaction
    {
        $reference = (string) ($response['merchant_txn'] ?? '');
        $transaction = ScholarshipWalletTransaction::query()
            ->where('scholarship_application_id', $application->id)
            ->when($reference !== '', fn ($query) => $query->where('reference', $reference))
            ->latest()
            ->first();

        if (! $transaction instanceof ScholarshipWalletTransaction) {
            throw ValidationException::withMessages(['wallet' => 'Wallet transaction request was not found.']);
        }

        return $transaction;
    }

    private function fraction(): string
    {
        $seed = date('ymdHis');

        return (string) (((int) $seed * 883) + 117);
    }
}
