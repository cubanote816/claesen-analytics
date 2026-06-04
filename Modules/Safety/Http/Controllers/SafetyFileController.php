<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Modules\Safety\Models\Answer;
use Modules\Safety\Models\Inspection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SafetyFileController extends Controller
{
    public function pdf(Inspection $inspection): StreamedResponse
    {
        Gate::authorize('downloadPdf', $inspection);

        $disk = Storage::disk(config('safety.disk'));

        if (! $inspection->pdf_path || ! $disk->exists($inspection->pdf_path)) {
            abort(404);
        }

        $filename = basename($inspection->pdf_path);

        return response()->stream(function () use ($disk, $inspection) {
            $stream = $disk->readStream($inspection->pdf_path);
            if ($stream === false) {
                return;
            }
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function photo(Answer $answer): StreamedResponse
    {
        $answer->load('inspection');

        if (! $answer->inspection) {
            abort(404);
        }

        Gate::authorize('viewPhoto', $answer->inspection);

        $disk = Storage::disk(config('safety.disk'));

        if (! $answer->photo_path || ! $disk->exists($answer->photo_path)) {
            abort(404);
        }

        $mimeType = $disk->mimeType($answer->photo_path) ?: 'application/octet-stream';

        return response()->stream(function () use ($disk, $answer) {
            $stream = $disk->readStream($answer->photo_path);
            if ($stream === false) {
                return;
            }
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type'  => $mimeType,
            'Cache-Control' => 'private, max-age=900',
        ]);
    }
}
