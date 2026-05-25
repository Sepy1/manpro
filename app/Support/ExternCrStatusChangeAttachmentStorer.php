<?php

namespace App\Support;

use App\Models\ExternCr;
use App\Models\ExternCrAttachment;
use App\Models\ExternCrHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ExternCrStatusChangeAttachmentStorer
{
    public const MAX_FILES = 3;

    /** @var list<string> */
    private const ALLOWED_EXT = [
        'pdf', 'doc', 'docx', 'rar', 'zip', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        return [
            'status_attachments' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'status_attachments.*' => [
                'file',
                'max:10240',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof \Illuminate\Http\UploadedFile) {
                        return;
                    }
                    $ext = Str::lower($value->getClientOriginalExtension());
                    if (! in_array($ext, self::ALLOWED_EXT, true)) {
                        $fail(__('Ekstensi file tidak diizinkan. Diperbolehkan: :ext', [
                            'ext' => implode(', ', self::ALLOWED_EXT),
                        ]));
                    }
                },
            ],
        ];
    }

    public static function hasUploads(Request $request): bool
    {
        foreach ($request->file('status_attachments', []) ?? [] as $upload) {
            if ($upload instanceof \Illuminate\Http\UploadedFile && $upload->isValid()) {
                return true;
            }
        }

        return false;
    }

    public static function storeForHistory(ExternCr $cr, ExternCrHistory $history, Request $request): int
    {
        $uploads = array_values(array_filter(
            $request->file('status_attachments', []) ?? [],
            static fn ($upload) => $upload instanceof \Illuminate\Http\UploadedFile && $upload->isValid()
        ));

        if ($uploads === []) {
            return 0;
        }

        $position = (int) $cr->attachments()->max('position');
        $stored = 0;
        $subdir = Str::slug($cr->nomor, '_').'/'.$cr->id.'/status';

        foreach (array_slice($uploads, 0, self::MAX_FILES) as $upload) {
            $position++;
            $path = $upload->store("extern_cr/{$subdir}", 'public');

            ExternCrAttachment::query()->create([
                'extern_cr_id' => $cr->id,
                'extern_cr_history_id' => $history->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => $upload->getClientOriginalName(),
                'mime' => $upload->getClientMimeType(),
                'size_bytes' => $upload->getSize() ?: null,
                'position' => $position,
            ]);

            $stored++;
        }

        return $stored;
    }

    public static function appendAttachmentSummaryToMessage(string $message, int $storedCount): string
    {
        if ($storedCount < 1) {
            return $message;
        }

        return $message.' ('.$storedCount.' lampiran disimpan)';
    }
}
