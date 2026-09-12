<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Handles the ONE side effect a leave-request attachment produces:
 * persisting the uploaded file to disk and handing back the stored path
 * so the eventual LeaveRequest row can reference it via
 * `attachment_path`.
 *
 * We can't reuse the Spatie MediaLibrary flow that TaskAttachmentService
 * uses because the LeaveRequest doesn't yet exist at upload time — the
 * employee attaches the file WHILE composing the request, then submits.
 * A plain Storage put (matching Employee::avatar_path's own contract
 * and the shape LeaveRequest::attachment_path already stores) fits that
 * two-step flow with no schema change.
 *
 * The chosen disk is `local` (private). Public-disk exposure would let
 * anyone with the URL download sensitive documents (sick notes, medical
 * certificates). Downloads are gated on a signed route
 * (`leaves.attachment.download`) resolved by LeaveRequestResource once
 * the leave row exists and its owner/HR can be authorized.
 *
 * File-type and size validation live in UploadLeaveAttachmentRequest —
 * this service assumes it receives an already-validated UploadedFile.
 */
class LeaveAttachmentService
{
    /**
     * Private disk — the file is only reachable through the signed
     * download route, never through a public URL.
     */
    public const DISK = 'local';

    /** Path prefix inside the disk — keeps leave files namespaced. */
    private const DIRECTORY_PREFIX = 'leave-attachments';

    /**
     * @return array{attachment_path: string}
     */
    public function store(User $user, UploadedFile $file): array
    {
        // Namespacing by user id ensures one employee can't overwrite
        // another's upload by racing on a colliding filename, and lets an
        // admin trace an orphaned file back to its uploader if a
        // submission is abandoned before it references the path. The
        // SubmitLeaveRequestRequest also uses this prefix to enforce
        // that a submitter can only reference files THEY uploaded — see
        // its `attachment_path` rule.
        $directory = self::DIRECTORY_PREFIX.'/'.$user->id;

        $filename = $this->generateFilename($file);

        $path = $file->storeAs($directory, $filename, self::DISK);

        return [
            'attachment_path' => $path,
        ];
    }

    /**
     * A random slug + preserved extension avoids leaking the original
     * (possibly PII-laden) filename in URLs while keeping the file type
     * clear for downstream renderers (browsers, PDF viewers).
     */
    private function generateFilename(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        return Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
    }

    /**
     * Prefix a given user_id's own uploads share. Used by
     * SubmitLeaveRequestRequest to authorize an `attachment_path`
     * against the caller (no cross-employee IDOR) and by the resource
     * layer to build download URLs.
     */
    public static function directoryFor(int $userId): string
    {
        return self::DIRECTORY_PREFIX.'/'.$userId.'/';
    }
}
