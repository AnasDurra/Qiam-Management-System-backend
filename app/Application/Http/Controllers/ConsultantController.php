<?php
namespace App\Application\Http\Controllers;
use App\Application\Http\Resources\ConsultantBriefResource;
use App\Application\Http\Resources\ConsultantResource;
use App\Domain\Services\ConsultantService;
use App\Domain\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ConsultantController extends Controller
{
    private ConsultantService $ConsultantService;

    public function __construct(ConsultantService $ConsultantService)
    {
        $this->ConsultantService = $ConsultantService;
    }

    public function index(): JsonResponse
    {
        $consultants = $this->ConsultantService->getConsultantList();
        return response()->json([
            'data'=>ConsultantBriefResource::collection($consultants)
            ], 200);
    }

    public function show(int $id): JsonResponse
    {
        $consultant = $this->ConsultantService->getConsultantById($id);
        if(!$consultant){
            return response()->json(['message'=>'Consultant not found']
                , 404);
        }

        return response()->json([
            'data'=> new ConsultantResource($consultant)
            ], 200);
    }

    public function store(): JsonResponse
    {
        $validator = Validator::make(request()->all(), [
            'clinic_id' => ['required','integer','exists:clinics,id'],
            'first_name' => ['required','max:75', 'string'],
            'last_name' => ['required','max:75', 'string'],
            'phone_number' => ['required','min:10','max:15', 'string',
                Rule::unique('consultants', 'phone_number')], //TODO ->whereNull('deleted_at')],
            'email' => ['required','max:40', 'email',
                Rule::unique('users', 'email')], //TODO ->whereNull('deleted_at')],

            'address' => ['required','max:100', 'string'],
            'birth_date' => ['required','date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response()->json([
                'data'=> $errors
            ], 400);
        }
        $consultant = $this->ConsultantService->createConsultant(request()->all());
        return response()->json([
            'data'=> new ConsultantResource($consultant)
            ], 200);
    }

    public function update(int $id): JsonResponse
    {
        $validator = Validator::make(request()->all(), [
            'clinic_id' => ['integer','exists:clinics,id'],
            'first_name' => ['max:75', 'string'],
            'last_name' => ['max:75', 'string'],
            'phone_number' => ['min:10','max:15', 'string',
                Rule::unique('consultants', 'phone_number')->ignore($id)], //TODO ->whereNull('deleted_at')],
            'email' => ['max:40', 'email',
                Rule::unique('users', 'email')], //TODO ->whereNull('deleted_at')],

            'address' => ['max:100', 'string'],
            'birth_date' => ['date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response()->json([
                'data'=> $errors
            ], 400);
        }

        $consultant = $this->ConsultantService->updateConsultant($id, request()->all());

        if(!$consultant){
            return response()->json(['message'=>'Consultant not found']
                , 404);
        }

        return response()->json([
            'data'=> new ConsultantResource($consultant)
            ], 200);
    }

    public function destroy(int $id): JsonResponse
    {
        $consultant = $this->ConsultantService->deleteConsultant($id);

        if(!$consultant){
            return response()->json(['message'=>'Consultant not found']
                , 404);
        }

        return response()->json([
            'data'=> new ConsultantResource($consultant)
            ], 200);
    }
}
