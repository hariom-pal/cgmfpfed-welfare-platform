<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Contracts\Services\ExportDefinitionInterface;
use App\Models\User;
use App\Services\RoleService;

final class UserExportDefinition implements ExportDefinitionInterface
{
    public function __construct(private readonly RoleService $roles) {}

    public function module(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'User Management';
    }

    public function availableFields(): array
    {
        return [
            'name' => 'Name',
            'email' => 'Email',
            'mobile' => 'Mobile',
            'role' => 'Role',
            'status' => 'Status',
            'district_union' => 'District Union',
            'samiti' => 'Samiti',
            'circle' => 'Circle',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    public function resolveRow(mixed $row): array
    {
        if (! $row instanceof User) {
            return [];
        }

        return [
            'name' => $row->name,
            'email' => $row->email,
            'mobile' => $row->mobile,
            'role' => $this->roles->name($row),
            'status' => $row->status === '1' ? 'Active' : 'Inactive',
            'district_union' => $row->districtUnionMaster?->name,
            'samiti' => $row->samitiMaster?->name,
            'circle' => $row->circleMaster?->name,
            'created_at' => $row->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $row->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
