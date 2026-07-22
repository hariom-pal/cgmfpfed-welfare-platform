<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class ScholarshipSessionService
{
    public function deriveForDate(CarbonInterface|string|null $date = null): ?AcademicSession
    {
        $applicationDate = $date instanceof CarbonInterface
            ? Carbon::instance($date)
            : Carbon::parse($date ?: now());

        $session = AcademicSession::query()
            ->whereDate('start_date', '<=', $applicationDate->toDateString())
            ->whereDate('end_date', '>=', $applicationDate->toDateString())
            ->orderByDesc('start_date')
            ->first();

        if ($session instanceof AcademicSession) {
            return $session;
        }

        $session = AcademicSession::query()
            ->whereDate('start_date', '<=', $applicationDate->toDateString())
            ->orderByDesc('start_date')
            ->first();

        if ($session instanceof AcademicSession) {
            return $session;
        }

        $session = AcademicSession::query()
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first();

        if ($session instanceof AcademicSession) {
            return $session;
        }

        return AcademicSession::query()
            ->orderByDesc('start_date')
            ->first();
    }

    public function nameForDate(CarbonInterface|string|null $date = null): ?string
    {
        return $this->deriveForDate($date)?->name;
    }
}
