<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\MasterRepository;
use App\Services\MasterService;
use App\Support\MasterRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MasterController extends Controller
{
    public function __construct(private readonly MasterRegistry $registry) {}

    public function index(Request $request, string $masterKey): JsonResponse
    {
        $master = $this->registry->get($masterKey);
        $service = new MasterService(new MasterRepository(app($master['model'])));

        return response()->json($service->paginate(
            ['search' => $request->string('search')->toString()],
            $request->string('sort', 'name')->toString(),
            $request->string('direction', 'asc')->toString(),
        ));
    }

    public function show(string $masterKey, string $uuid): JsonResponse
    {
        $master = $this->registry->get($masterKey);
        $service = new MasterService(new MasterRepository(app($master['model'])));

        return response()->json($service->findByUuid($uuid));
    }
}
