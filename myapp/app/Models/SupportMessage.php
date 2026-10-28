<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupportMessage extends Model
{
    use BelongsToUser;

    public $timestamps = false;

    protected $guarded = ['user_id'];

    protected function casts(): array
    {
        return ['from_admin' => 'boolean', 'read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    /** Columns for an uploaded file, stored on the private disk; the type is read from the file itself, not from what the browser claims. */
    public static function fileColumns(?UploadedFile $file, int $threadId): array
    {
        if (! $file) {
            return [];
        }

        $name = Str::ulid().'.'.$file->extension();

        return [
            'attachment_path' => Storage::putFileAs("support/{$threadId}", $file, $name),
            'attachment_name' => Str::limit($file->getClientOriginalName(), 100, ''),
            'attachment_mime' => $file->getMimeType(),
            'attachment_size' => $file->getSize(),
        ];
    }

    /** What the chat page needs from a message; same shape for the user and the admin. */
    public function toClient(): array
    {
        return [
            'id' => $this->id, 'body' => $this->body, 'from_admin' => $this->from_admin, 'at' => $this->created_at->toIso8601String(), 'seen' => $this->read_at !== null,
            'file' => $this->attachment_path ? ['name' => $this->attachment_name, 'size' => $this->attachment_size, 'image' => str_starts_with((string) $this->attachment_mime, 'image/')] : null,
        ];
    }

    /** Streams the attachment: images inline, PDFs as downloads, never sniffed into another type, never cached. */
    public function download()
    {
        abort_unless($this->attachment_path && Storage::exists($this->attachment_path), 404);

        return Storage::response(
            $this->attachment_path,
            $this->attachment_name,
            ['Content-Type' => $this->attachment_mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'],
            str_starts_with((string) $this->attachment_mime, 'image/') ? 'inline' : 'attachment',
        );
    }
}
