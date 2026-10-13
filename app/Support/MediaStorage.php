<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class MediaStorage
{
    public static function storeProof(UploadedFile $file, string $folder = 'proofs'): string
    {
        $year = Carbon::now()->format('Y');
        $month = Carbon::now()->format('m');
        $filename = Str::ulid().'.jpg';
        $relativeDir = "{$folder}/{$year}/{$month}";
        $fullPath = "{$relativeDir}/{$filename}";

        Storage::disk('local')->putFileAs($relativeDir, $file, $filename);

        return $fullPath;
    }

    public static function temporaryUrl(string $path, int $minutes = 15): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        try {
            return Storage::disk('local')->temporaryUrl($path, now()->addMinutes($minutes));
        } catch (\Throwable) {
            return url('/api/media/preview?path='.urlencode($path));
        }
    }

    public static function delete(string $path): bool
    {
        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->delete($path);
        }

        return false;
    }
}
