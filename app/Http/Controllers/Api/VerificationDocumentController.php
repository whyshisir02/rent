<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Models\HouseBill;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Landlord;
use App\Models\Tenant;
class VerificationDocumentController extends Controller
{
    // GET /verification-documents — list the authenticated user's own documents and bills
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->user_type === 'landlord' ? $user->landlord : $user->tenant;

        return response()->json([
            'data' => [
            'verification_status' => $profile?->verification_status ?? 'pending',
            'verification_rejection_reason' => $profile?->verification_rejection_reason,
            'verified_by' => $profile?->verified_by
                ? optional(User::find($profile->verified_by))->phone
                : null,
                'verified_at' => $profile?->verified_at?->toDateTimeString(),
                'documents' => $user->verificationDocuments,
                'house_bills' => $user->houseBills,
            ],
        ]);
    }

    // POST /verification-documents — upload documents (multipart/form-data)
    // documents[0][type] = citizenship_front, documents[0][file] = <file>  (everyone)
    // house_bills[0][type] = electricity_bill, house_bills[0][file] = <file> (landlords only)
    // On successful upload the profile is set to `pending` (if still `unverified`).
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->user_type === 'admin') {
            return response()->json([
                'message' => 'Admins cannot upload verification documents.',
            ], 403);
        }

        $isLandlord = $user->user_type === 'landlord';

        // Flexible: at least one of documents/house_bills must be present.
        // Landlords may submit in multiple requests; tenants cannot submit bills.
        $validator = Validator::make($request->all(), [
            'documents' => 'nullable|array|min:1|required_without:house_bills',
            'documents.*.type' => 'required_with:documents|string|max:100',
            'documents.*.file' => 'required_with:documents|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',

            'house_bills' => [
                $isLandlord ? 'nullable' : 'prohibited',
                'array',
                'min:1',
                'required_without:documents',
            ],
            'house_bills.*.type' => 'required_with:house_bills|string|max:100',
            'house_bills.*.file' => 'required_with:house_bills|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $folder = $user->uploadFolderName();

        DB::beginTransaction();

        try {
            $uploaded = ['documents' => [], 'house_bills' => []];

            // hasFile() is unreliable for nested array uploads; use the files bag directly
            $documentFiles = $request->input('documents')
                ? ($request->allFiles()['documents'] ?? [])
                : [];
            $billFiles = $isLandlord && $request->input('house_bills')
                ? ($request->allFiles()['house_bills'] ?? [])
                : [];

            foreach ($documentFiles as $index => $document) {
                $type = $request->input("documents.{$index}.type");
                $file = $document['file'];

                $path = $file->store('verification-documents/' . $folder, 'public');

                $uploaded['documents'][] = $user->verificationDocuments()->create([
                    'document_type' => $type,
                    'document_url' => Storage::disk('public')->url($path),
                ]);
            }

            foreach ($billFiles as $index => $bill) {
                $type = $request->input("house_bills.{$index}.type");
                $file = $bill['file'];

                $path = $file->store('house-bills/' . $folder, 'public');

                $uploaded['house_bills'][] = $user->houseBills()->create([
                    'document_type' => $type,
                    'document_url' => Storage::disk('public')->url($path),
                ]);
            }

            if (empty($uploaded['documents']) && empty($uploaded['house_bills'])) {
                throw new \InvalidArgumentException('Nothing to upload.');
            }

            // Set profile to pending on submission (if not already approved)
            $profile = $user->landlord ?? $user->tenant;
            if ($profile && in_array($profile->verification_status, ['approved', 'rejected'])) {
                $profile->update(['verification_status' => 'pending']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Documents uploaded successfully',
                'data' => $uploaded,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Document upload failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to upload documents',
            ], 500);
        }
    }

    public function download(Request $request, string $type, int $id): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->user_type === 'admin';

        $document = $type === 'house-bill'
            ? HouseBill::findOrFail($id)
            : VerificationDocument::findOrFail($id);

        if (!$isAdmin && $document->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $path = Str::replaceFirst(Storage::disk('public')->url(''), '', $document->document_url);

        if (!$path || !Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        return response()->json([
            'url' => '/documents/' . $path,
            'mime_type' => Storage::disk('public')->mimeType($path),
        ]);
    }

    // GET /documents/{path} — stream a stored verification file (auth required)
    public function streamFile(Request $request, string $path): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->user();
        $isAdmin = $user->user_type === 'admin';

        $fullPath = 'public/' . $path;

        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'File not found');
        }

        if (!$isAdmin) {
            $allowed = $user->verificationDocuments()
                ->where('document_url', 'LIKE', "%/" . $path)
                ->exists();
            $allowed = $allowed || $user->houseBills()
                ->where('document_url', 'LIKE', "%/" . $path)
                ->exists();

            if (!$allowed) {
                abort(403, 'Forbidden');
            }
        }

        return Storage::disk('public')->response($path);
    }

    // GET /admin/users — admin: list all non-admin users with verification status + doc counts
    public function adminUserIndex(Request $request): JsonResponse
    {
        $users = User::where('user_type', '!=', 'admin')
            ->with(['landlord', 'tenant', 'verificationDocuments', 'houseBills'])
            ->paginate(15);

        $data = $users->through(function ($user) {
            $profile = $user->user_type === 'landlord' ? $user->landlord : $user->tenant;

            return [
                'id' => $user->id,
                'phone' => $user->phone,
                'user_type' => $user->user_type,
                'status' => $user->status,
                'verification_status' => $profile?->verification_status ?? 'pending',
                'verification_rejection_reason' => $profile?->verification_rejection_reason,
                'verified_by' => $profile?->verified_by
                    ? User::find($profile->verified_by)?->phone
                    : null,
                'verified_at' => $profile?->verified_at?->toDateTimeString(),
                'document_count' => $user->verificationDocuments->count(),
                'house_bill_count' => $user->houseBills->count(),
            ];
        });

        return response()->json($data);
    }

    // GET /admin/users/{user}/verification — admin: view any user's documents + profile status
    public function adminShowVerification(User $user): JsonResponse
    {
        $profile = $user->user_type === 'landlord' ? $user->landlord : $user->tenant;

        return response()->json([
            'data' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'user_type' => $user->user_type,
                'verification_status' => $profile?->verification_status ?? 'pending',
                'verification_rejection_reason' => $profile?->verification_rejection_reason,
                'verified_by' => $profile?->verified_by
                    ? User::find($profile->verified_by)?->phone
                    : null,
                'verified_at' => $profile?->verified_at?->toDateTimeString(),
                'documents' => $user->verificationDocuments()->get(),
                'house_bills' => $user->houseBills()->get(),
            ],
        ]);
    }
}