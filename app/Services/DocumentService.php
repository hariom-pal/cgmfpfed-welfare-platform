<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ScholarshipApplicationDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentService
{
    public function serve(ScholarshipApplicationDocument $document, bool $forceDownload = false): StreamedResponse|RedirectResponse|Response
    {
        $disk = $document->storage_disk ?: (string) config('scholarship_documents.disk', 'public');
        $path = $this->storagePath($document);
        $downloadName = $document->displayName();

        if ($disk === 's3') {
            try {
                if (Storage::disk('s3')->exists($path)) {
                    $url = Storage::disk('s3')->temporaryUrl(
                        $path,
                        now()->addMinutes((int) config('scholarship_documents.temporary_url_minutes', 5)),
                        $this->s3ResponseOptions($document, $forceDownload, $downloadName),
                    );

                    return redirect()->away($url);
                }
            } catch (Throwable $exception) {
                Log::warning('Unable to serve scholarship document from S3.', [
                    'document_id' => $document->id,
                    'path' => $path,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($disk !== 's3' && Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->response(
                $path,
                $downloadName,
                $this->headers($document),
                $this->disposition($document, $forceDownload),
            );
        }

        $legacyPath = $this->legacyLocalPath($path);
        if (! is_file($legacyPath)) {
            return response('Document not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return response()->streamDownload(
            static function () use ($legacyPath): void {
                readfile($legacyPath);
            },
            $downloadName,
            $this->headers($document),
            $this->disposition($document, $forceDownload),
        );
    }

    public function storagePath(ScholarshipApplicationDocument $document): string
    {
        $path = ltrim((string) $document->file_path, '/');

        if (($document->storage_disk ?: null) === 's3' && ! str_contains($path, '/')) {
            $prefix = (string) config('scholarship_documents.s3_prefix', '');

            return $prefix !== '' ? $prefix.'/'.$path : $path;
        }

        return $path;
    }

    private function legacyLocalPath(string $path): string
    {
        $filename = basename($path);
        $info = pathinfo($filename);
        $name = str_replace([' ', '.'], '_', (string) ($info['filename'] ?? $filename));
        $extension = (string) ($info['extension'] ?? '');
        $normalized = $extension !== '' ? $name.'.'.$extension : $name;

        return rtrim((string) config('scholarship_documents.local_legacy_upload_path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$normalized;
    }

    /**
     * @return array<string, string>
     */
    private function headers(ScholarshipApplicationDocument $document): array
    {
        return array_filter([
            'Content-Type' => $document->mime_type ?: null,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function s3ResponseOptions(ScholarshipApplicationDocument $document, bool $forceDownload, string $downloadName): array
    {
        return array_filter([
            'ResponseContentType' => $document->mime_type ?: null,
            'ResponseContentDisposition' => $this->disposition($document, $forceDownload).'; filename="'.$downloadName.'"',
        ]);
    }

    private function disposition(ScholarshipApplicationDocument $document, bool $forceDownload): string
    {
        return $forceDownload || ! $document->shouldOpenInline() ? 'attachment' : 'inline';
    }
}
