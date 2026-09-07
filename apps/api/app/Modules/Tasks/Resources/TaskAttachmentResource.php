<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Resources;

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
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'uploaded_by' => $this->getCustomProperty('uploaded_by'),
            'download_url' => URL::temporarySignedRoute(
                'tasks.attachments.download',
                now()->addMinutes(self::DOWNLOAD_LINK_LIFETIME_MINUTES),
                ['media' => $this->id],
            ),
            'created_at' => $this->created_at,
        ];
    }
}
