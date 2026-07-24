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
 * Automated counterpart to legacy `Payment::uploadCsv()` (manual CSV upload) — reads every CSV
 * file dropped into the reverse-feed source directory, reconciles matched applications' payment
 * results, then archives the processed file. Column layout matches legacy exactly:
 * column 1 = application_number, column 2 = "Failed"/"FAILED" or the payment UTR reference,
 * column 3 = failure reason (ignored on success).
 */
#[Signature('scholarship:reconcile-axis-reverse-feed')]
#[Description('Reconcile AXIS bank reverse-feed CSV files into scholarship payment results, then archive each processed file.')]
final class ReconcileAxisReverseFeed extends Command
{
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

        $files = glob(rtrim($sourcePath, '/').'/*.csv') ?: [];
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
            'Reconciled %d application(s), skipped %d row(s), across %d file(s).',
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
        $handle = fopen($file, 'r');
        if ($handle === false) {
            $this->error("Unable to open [{$file}].");

            return [0, 0];
        }

        $reconciled = 0;
        $skipped = 0;
        $rowNumber = 0;

        while (($row = fgetcsv($handle, 1024)) !== false) {
            $rowNumber++;
            if ($rowNumber === 1) {
                continue;
            }

            $applicationNumber = trim((string) ($row[1] ?? ''));
            $statusOrReference = trim((string) ($row[2] ?? ''));
            $reason = trim((string) ($row[3] ?? ''));

            $application = ScholarshipApplication::query()
                ->where('application_number', $applicationNumber)
                ->first();

            if (! $application instanceof ScholarshipApplication
                || (int) $application->status !== ScholarshipApplicationStatus::PaymentBatchSubmitted->value) {
                $skipped++;

                continue;
            }

            $failed = in_array(strtoupper($statusOrReference), ['FAILED'], true);

            $service->recordPaymentResult(
                $application,
                ! $failed,
                $failed ? null : $statusOrReference,
                $failed ? $reason : null,
                $actor,
                ['reverse_feed_file' => basename($file), 'reverse_feed_row' => $rowNumber],
            );
            $reconciled++;
        }

        fclose($handle);

        return [$reconciled, $skipped];
    }
}
