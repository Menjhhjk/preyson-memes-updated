<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class PostMedia implements ValidationRule
{
    public const MAX_UPLOAD_KB = 102400;

    public const MAX_UPLOAD_BYTES = self::MAX_UPLOAD_KB * 1024;

    // Validate server-detected MIME, not a filename or a browser-provided type.
    // In particular, .ts and .mts can also be TypeScript source files.
    private const MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'video/mp4' => ['mp4', 'm4v'],
        'video/x-m4v' => ['m4v', 'mp4'],
        'video/webm' => ['webm'],
        'video/quicktime' => ['mov'],
        'video/matroska' => ['mkv'],
        'video/x-matroska' => ['mkv'],
        'application/x-matroska' => ['mkv'],
        'video/x-flv' => ['flv'],
        'video/flv' => ['flv'],
        'application/x-flash-video' => ['flv'],
        'video/x-msvideo' => ['avi'],
        'video/avi' => ['avi'],
        'video/msvideo' => ['avi'],
        'video/vnd.avi' => ['avi'],
        'video/x-avi' => ['avi'],
        'video/mpeg' => ['mpeg', 'mpg'],
        'video/mp2t' => ['ts', 'mts', 'm2ts'],
        'video/x-ms-wmv' => ['wmv'],
        'video/x-ms-asf' => ['wmv'],
        'application/vnd.ms-asf' => ['wmv'],
        'video/ogg' => ['ogv'],
    ];

    public const FORMAT_LABEL = 'JPG, PNG, WebP, GIF; MP4, WebM, MOV, MKV, FLV, AVI, M4V, MPEG/MPG, TS/MTS/M2TS, WMV, OGV';

    public static function validationRule(): self
    {
        return new self;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()
            || ! in_array(strtolower($value->getClientOriginalExtension()), self::extensions(), true)
            || ! isset(self::MIME_EXTENSIONS[self::mimeType($value)])) {
            $fail('Choose a supported image or video recording: '.self::FORMAT_LABEL.'. The file contents must match a supported format.');
        }
    }

    /** @return list<string> */
    public static function extensions(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::MIME_EXTENSIONS))));
    }

    public static function accept(): string
    {
        return implode(',', array_map(fn (string $extension) => '.'.$extension, self::extensions()));
    }

    public static function extension(UploadedFile $file): string
    {
        $extensions = self::MIME_EXTENSIONS[self::mimeType($file)] ?? [];
        if ($extensions === []) {
            throw ValidationException::withMessages(['media' => 'Choose a supported image, GIF, or video recording.']);
        }

        $original = strtolower($file->getClientOriginalExtension());

        return in_array($original, $extensions, true) ? $original : $extensions[0];
    }

    public static function category(UploadedFile $file): string
    {
        $mime = self::mimeType($file);

        return $mime === 'image/gif' ? 'gif' : (str_starts_with($mime, 'image/') ? 'image' : 'video');
    }

    private static function mimeType(UploadedFile $file): string
    {
        // MIME tokens are case-insensitive and Fileinfo casing varies by PHP
        // version (PHP 8.5 reports video/MP2T). Normalize before map lookups.
        $mime = strtolower($file->getMimeType() ?? '');

        // Fileinfo may label any 0x47-prefixed payload (including junk) as a
        // transport stream, so .ts/.mts/.m2ts uploads must always pass the
        // bounded packet-framing check below instead of trusting the type.
        if (in_array(strtolower($file->getClientOriginalExtension()), ['ts', 'mts', 'm2ts'], true)) {
            return self::hasTransportPackets($file) ? 'video/mp2t' : 'application/octet-stream';
        }

        return $mime;
    }

    private static function hasTransportPackets(UploadedFile $file): bool
    {
        $stream = fopen($file->getPathname(), 'rb');
        if ($stream === false) {
            return false;
        }
        try {
            $header = fread($stream, 5 * 204);
        } finally {
            fclose($stream);
        }
        if ($header === false) {
            return false;
        }

        // Standard TS, timestamp-prefixed M2TS, and error-corrected TS packets.
        foreach ([[188, 0], [192, 4], [204, 0]] as [$packetSize, $offset]) {
            if (strlen($header) < 5 * $packetSize) {
                continue;
            }
            for ($packet = 0; $packet < 5; $packet++) {
                $start = $packet * $packetSize + $offset;
                $control = (ord($header[$start + 3]) >> 4) & 3;
                if ($header[$start] !== "\x47" || $control === 0) {
                    continue 2;
                }
                if ($control >= 2 && ord($header[$start + 4]) > ($control === 2 ? 183 : 182)) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }
}
