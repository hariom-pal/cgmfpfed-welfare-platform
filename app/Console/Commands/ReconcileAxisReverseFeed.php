<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Scholarship\Contracts\ScholarshipServiceInterface;
use App\Domains\Scholarship\Enums\ScholarshipApplicationStatus;
use App\Models\ScholarshipApplication;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Reconciles AXIS Bank's real settlement-response feed files (named `axis_reversefeed_*`,
 * `^`-delimited, no header row, no file extension) into scholarship payment results, then
 * archives each processed file. This feed is shared across CGMFPFED's modules (e.g. Beema) —
 * a row whose reference (field 0/9) doesn't match any `scholarship_applications.application_number`
 * simply belongs to another module and is skipped, never treated as an error.
 *
 * Field layout (0-indexed, confirmed against real sample data, both SUCCESS and REJECTED rows):
 *   [0]/[9]  our payment reference == scholarship_applications.application_number
 *   [6]      status: "SUCCESS" on success; a rejection reason code otherwise (e.g. "REJECTED")
 *   [7]      "Success--{UTR}" on success; a human-readable failure reason on failure
 *   [11]     bank transaction reference (present on both success and failure, "CN..."/"CX..." prefix)
 *   [12]     amount
 */
#[Signature('scholarship:reconcile-axis-reverse-feed')]
#[Description('Reconcile AXIS bank reverse-feed files into scholarship payment results, then archive each processed file.')]
final class ReconcileAxisReverseFeed extends Command
{
    private const int FIELD_REFERENCE = 0;

    private const int FIELD_STATUS = 6;

    private const int FIELD_MESSAGE = 7;

    private const int FIELD_BANK_TXN_REFERENCE = 11;

    private const int FIELD_AMOUNT = 12;

    public function handle(ScholarshipServiceInterface $service): int
    {
        $sourcePath = (string) config('axis_payment.reverse_feed_source_path');
        $archivePath = (string) config('axis_payment.reverse_feed_archive_path');
        $actorEmail = config('axis_payment.reconciliation_actor_email');

        if (! is_string($actorEmail) || trim($actorEmail) === '') {
            $this->error('axis_payment.reconciliation_actor_email is not configured — refusing to run unattended.');

            return self::FAILURE;
        }

        $actor = User::query()->where('email', $actorEmail)->first();
        if (! $actor instanceof User) {
            $this->error("No user found with email [{$actorEmail}] configured as axis_payment.reconciliation_actor_email.");

            return self::FAILURE;
        }

        if (! is_dir($sourcePath)) {
            $this->components->info("Reverse-feed source directory [{$sourcePath}] does not exist yet — nothing to reconcile.");

            return self::SUCCESS;
        }

        if (! is_dir($archivePath)) {
            mkdir($archivePath, 0755, true);
        }

        $files = glob(rtrim($sourcePath, '/').'/axis_reversefeed_*') ?: [];
        $reconciled = 0;
        $skipped = 0;

        foreach ($files as $file) {
            [$fileReconciled, $fileSkipped] = $this->reconcileFile($file, $service, $actor);
            $reconciled += $fileReconciled;
            $skipped += $fileSkipped;

            $archiveTarget = rtrim($archivePath, '/').'/'.now()->format('YmdHis').'-'.basename($file);
            rename($file, $archiveTarget);
        }

        $this->components->info(sprintf(
            'Reconciled %d application(s), skipped %d row(s) (not ours or not awaiting a result), across %d file(s).',
            $reconciled,
            $skipped,
            count($files),
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int} [reconciled count, skipped count]
     */
    private function reconcileFile(string $file, ScholarshipServiceInterface $service, User $actor): array
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $this->error("Unable to open [{$file}].");

            return [0, 0];
        }

        $reconciled = 0;
        $skipped = 0;

        foreach ($lines as $lineNumber => $line) {
            $fields = explode('^', $line);
            $reference = trim($fields[self::FIELD_REFERENCE]);

            if ($reference === '') {
                $skipped++;

                continue;
            }

            $application = ScholarshipApplication::query()
                ->where('application_number', $reference)
                ->first();

            if (! $application instanceof ScholarshipApplication
                || (int) $application->status !== ScholarshipApplicationStatus::PaymentBatchSubmitted->value) {
                // Not one of ours (e.g. a Beema reference on the same shared feed), or already settled.
                $skipped++;

                continue;
            }

            $success = strtoupper(trim($fields[self::FIELD_STATUS] ?? '')) === 'SUCCESS';
            $bankTransactionReference = trim($fields[self::FIELD_BANK_TXN_REFERENCE] ?? '') ?: null;
            $message = trim($fields[self::FIELD_MESSAGE] ?? '');

            $service->recordPaymentResult(
                $application,
                $success,
                $success ? $bankTransactionReference : null,
                $success ? null : $message,
                $actor,
                [
                    'reverse_feed_file' => basename($file),
                    'reverse_feed_line' => $lineNumber + 1,
                    'bank_transaction_reference' => $bankTransactionReference,
                    'amount' => trim($fields[self::FIELD_AMOUNT] ?? ''),
                ],
            );
            $reconciled++;
        }

        return [$reconciled, $skipped];
    }
}
