<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin Media
 */
class TaskAttachmentResource extends JsonResource
{
    private const DOWNLOAD_LINK_LIFETIME_MINUTES = 30;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $uploadedById = $this->getCustomProperty('uploaded_by');
        $uploadedById = $uploadedById === null ? null : (int) $uploadedById;

        // NOTE: resolving the User -> Employee here fires one query per
        // attachment. TaskAttachmentController::index should eager-load
        // the uploader users (with their employee) up-front to avoid an
        // N+1 — batch-load in the controller if you see slowness.
        $uploader = null;
        if ($uploadedById !== null) {
            $user = User::query()->with('employee')->find($uploadedById);
            if ($user !== null) {
                $uploader = [
                    'id' => $user->id,
                    'full_name' => $user->employee?->full_name ?? $user->name,
                ];
            }
        }

        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'uploaded_by_id' => $uploadedById,
            'uploaded_by' => $uploader,
            'download_url' => URL::temporarySignedRoute(
                'tasks.attachments.download',
                now()->addMinutes(self::DOWNLOAD_LINK_LIFETIME_MINUTES),
                ['media' => $this->id],
            ),
            'created_at' => $this->created_at,
        ];
    }
}
