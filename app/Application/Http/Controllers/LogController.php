<?php

namespace App\Application\Http\Controllers;

use App\Application\Http\Resources\LogResource;
use App\Domain\Services\LogService;
use App\Http\Resources\ActionResource;
use App\Http\Resources\AffectedUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;

class LogController extends Controller
{
    private LogService $LogService;

    public function __construct(LogService $LogService)
    {
        $this->LogService = $LogService;
    }

    public function getAllAction(): ActionResource
    {
        $logs = $this->LogService->getAllAction();
        return new ActionResource($logs);
    }

    public function getAllAffectedUser(): AnonymousResourceCollection
    {
        $logs = $this->LogService->getAllAffectedUser();
        return AffectedUserResource::collection($logs);
    }

    public function getAllUser(): AnonymousResourceCollection
    {
        $logs = $this->LogService->getAllUser();
        return AffectedUserResource::collection($logs);
    }

    public function getLog(): AnonymousResourceCollection
    {
        $logs = $this->LogService->getLog();
        return LogResource::collection($logs);
    }

}
