<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ScholarshipLookupController extends Controller
{
    public function districtUnions(Request $request): JsonResponse
    {
        return response()->json($this->archiveRows('district_union', 'union_name', [
            'district_code' => $request->string('district_code')->toString(),
        ])->map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['union_name'],
        ]));
    }

    public function samitis(Request $request): JsonResponse
    {
        return response()->json($this->archiveRows('samiti', 'samiti_name', [
            'district_union_id' => $request->string('district_union_id')->toString(),
        ])->map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['samiti_name'],
        ]));
    }

    public function phads(Request $request): JsonResponse
    {
        return response()->json($this->archiveRows('phads', 'phad_name', [
            'samiti_id' => $request->string('samiti_id')->toString(),
        ])->map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) $row['phad_code'],
            'name' => (string) $row['phad_name'],
        ]));
    }

    public function blocks(Request $request): JsonResponse
    {
        return response()->json($this->archiveRows('blocks', 'block_name', [
            'district_code' => $request->string('district_code')->toString(),
        ])->map(fn (array $row): array => [
            'code' => (string) $row['block_code'],
            'name' => (string) $row['block_name'],
        ]));
    }

    public function gramPanchayats(Request $request): JsonResponse
    {
        return response()->json($this->archiveRows('gram_panchayat', 'gp_name', [
            'block_code' => $request->string('block_code')->toString(),
        ])->map(fn (array $row): array => [
            'code' => (string) $row['gp_code'],
            'name' => (string) $row['gp_name'],
        ]));
    }

    public function villages(Request $request): JsonResponse
    {
        return response()->json($this->archiveRows('villages', 'village_name', [
            'gp_code' => $request->string('gram_panchayat_code')->toString(),
        ])->map(fn (array $row): array => [
            'code' => (string) $row['village_code'],
            'name' => (string) $row['village_name'],
        ]));
    }

    public function cities(Request $request): JsonResponse
    {
        return response()->json($this->archiveRows('cities', 'city_name', [
            'block_code' => $request->string('block_code')->toString(),
        ])->map(fn (array $row): array => [
            'code' => (string) $row['city_code'],
            'name' => (string) $row['city_name'],
        ]));
    }

    public function wards(Request $request): JsonResponse
    {
        return response()->json($this->archiveRows('wards', 'ward_name', [
            'city_code' => $request->string('city_code')->toString(),
        ])->map(fn (array $row): array => [
            'code' => (string) $row['ward_code'],
            'number' => (string) $row['ward_number'],
            'name' => (string) $row['ward_name'],
        ]));
    }

    /**
     * @param  array<string, string>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function archiveRows(string $table, string $sortColumn, array $filters): Collection
    {
        $rows = DB::table('source_data_archives')
            ->where('source_table', $table)
            ->get(['payload'])
            ->map(fn (object $row): array => $this->decodePayload((string) $row->payload));

        foreach ($filters as $column => $value) {
            if ($value === '') {
                continue;
            }

            $rows = $rows->filter(fn (array $row): bool => (string) Arr::get($row, $column, '') === $value);
        }

        return $rows->sortBy(fn (array $row): string => (string) Arr::get($row, $sortColumn, ''))->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $payload): array
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return [];
        }

        $row = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $row[$key] = $value;
            }
        }

        return $row;
    }
}
