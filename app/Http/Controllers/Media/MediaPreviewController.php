<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a proof photo kept on the local disk.
 *
 * MediaStorage::temporaryUrl cannot sign a URL for a driver that has no
 * temporary URLs, and the local disk is one, so for a stored (relative) photo
 * this endpoint is the only URL that ever exists. It is what the console renders
 * in the shipment detail's proof card.
 */
final class MediaPreviewController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $path = (string) $request->query('path', '');

        // Only the shape MediaStorage writes: <folder>/<YYYY>/<MM>/<file>.<ext>.
        // Anything else — traversal, absolute paths, other folders — is not a
        // proof photo and is refused before the disk is touched.
        if (preg_match('#^[a-z0-9_-]+/\d{4}/\d{2}/[A-Za-z0-9_-]+\.[A-Za-z0-9]+$#', $path) !== 1) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path);
    }
}
