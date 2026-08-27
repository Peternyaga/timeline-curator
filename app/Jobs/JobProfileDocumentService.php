<?php

namespace App\Jobs;

use App\Models\JobProfile;
use App\Models\JobProfileDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JobProfileDocumentService
{
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function store(JobProfile $profile, UploadedFile $file, string $kind, string $label, bool $isDefault): JobProfileDocument
    {
        if ($profile->documents()->count() >= 10) {
            throw ValidationException::withMessages(['document' => 'A profile may contain at most 10 documents.']);
        }

        $contents = $file->get();
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages(['document' => 'Upload a genuine PDF or DOCX document.']);
        }

        $path = 'job-profile-documents/'.$profile->tenant_id.'/'.Str::ulid().'.enc';
        Storage::disk('local')->put($path, Crypt::encryptString(base64_encode($contents)));

        if ($kind === 'resume' && $isDefault) {
            $profile->documents()->where('kind', 'resume')->update(['is_default' => false]);
        }

        return $profile->documents()->create([
            'kind' => $kind,
            'label' => $label,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size_bytes' => strlen($contents),
            'storage_path' => $path,
            'sha256' => hash('sha256', $contents),
            'is_default' => $kind === 'resume' && $isDefault,
        ]);
    }

    public function contents(JobProfileDocument $document): string
    {
        $encrypted = Storage::disk('local')->get($document->storage_path);

        return base64_decode(Crypt::decryptString($encrypted), true) ?: '';
    }

    public function delete(JobProfileDocument $document): void
    {
        Storage::disk('local')->delete($document->storage_path);
        $document->delete();
    }
}
