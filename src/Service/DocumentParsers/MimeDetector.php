<?php

namespace BoldMinded\DexterCore\Service\DocumentParsers;

use League\MimeTypeDetection\FinfoMimeTypeDetector;
use RuntimeException;

final class MimeDetector
{
    private const MAX_SNIFF = 262_144; // 256 KiB
    private FinfoMimeTypeDetector $detector;

    public function __construct(?FinfoMimeTypeDetector $detector = null)
    {
        $this->detector = $detector ?? new FinfoMimeTypeDetector();
    }

    public function detect(string $filePath): string
    {
        // Remote files
        if ($this->isHttpUrl($filePath)) {
            return $this->detectRemoteFile($filePath);
        }

        return $this->detectLocalFile($filePath);
    }

    private function detectRemoteFile(string $filePath): string
    {
        $ct = $this->remoteContentType($filePath);
        if ($ct && !$this->isGeneric($ct)) {
            return strtolower($ct);
        }

        // Sniff first N bytes and hint with the path (for extension fallback)
        $bytes = $this->readRemoteBytes($filePath, self::MAX_SNIFF);
        if ($bytes !== '') {
            // Prefer detectMimeType(path, buffer) so extension can help
            $mime = $this->detector->detectMimeType($this->basenameFromUrl($filePath), $bytes)
                ?? $this->detector->detectMimeTypeFromBuffer($bytes);
            if (is_string($mime) && $mime !== '' && !$this->isGeneric($mime)) {
                return strtolower($mime);
            }
        }

        // Finally, fallback to extension only
        $mime = $this->detector->detectMimeTypeFromPath($this->basenameFromUrl($filePath));
        if (is_string($mime) && $mime !== '') {
            return strtolower($mime);
        }

        throw new RuntimeException(sprintf('Could not detect MIME type for: %s', $filePath));
    }

    private function detectLocalFile(string $filePath): string
    {
        // Local files use league + guard finfo warnings with a temporary handler
        $prev = set_error_handler(function (int $errno, string $errstr) {
            if ($errno === E_WARNING && (str_contains($errstr, 'finfo') || str_contains($errstr, 'identify data'))) {
                return true; // swallow only finfo warnings
            }
            return false;
        });

        try {
            $mime = $this->detector->detectMimeTypeFromFile($filePath);
        } finally {
            if ($prev !== null) set_error_handler($prev); else restore_error_handler();
        }

        if (is_string($mime) && $mime !== '' && !$this->isGeneric($mime)) {
            return strtolower($mime);
        }

        // As a last resort for locals, use extension
        $mime = $this->detector->detectMimeTypeFromPath($filePath);
        if (is_string($mime) && $mime !== '') {
            return strtolower($mime);
        }

        throw new RuntimeException(sprintf('Could not detect MIME type for: %s', $filePath));
    }

    private function isHttpUrl(string $p): bool
    {
        return str_starts_with($p, 'http://') || str_starts_with($p, 'https://');
    }

    private function isGeneric(string $m): bool
    {
        $m = strtolower($m);
        return $m === 'application/octet-stream' || $m === 'binary/octet-stream';
    }

    private function basenameFromUrl(string $url): string
    {
        $parts = parse_url($url);
        return isset($parts['path']) ? basename($parts['path']) : 'file';
    }

    private function remoteContentType(string $url): ?string
    {
        // Prefer cURL HEAD
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT => 'LeagueMimeDetector/1.0',
            ]);
            $ok = curl_exec($ch);
            $ct = $ok !== false ? curl_getinfo($ch, CURLINFO_CONTENT_TYPE) : null;
            curl_close($ch);
            if (is_string($ct) && $ct !== '') {
                return strtolower(trim(explode(';', $ct, 2)[0]));
            }
            return null;
        }

        // Stream fallback
        $ctx = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => 8, 'ignore_errors' => true]]);
        $fp = @fopen($url, 'r', false, $ctx);
        if ($fp === false) return null;
        $meta = stream_get_meta_data($fp);
        @fclose($fp);
        if (!empty($meta['wrapper_data'])) {
            foreach ($meta['wrapper_data'] as $h) {
                if (stripos($h, 'Content-Type:') === 0) {
                    $ct = trim(substr($h, 13));
                    return strtolower(trim(explode(';', $ct, 2)[0]));
                }
            }
        }
        return null;
    }

    private function readRemoteBytes(string $url, int $bytes): string
    {
        // cURL with Range (fast, small)
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT => 'LeagueMimeDetector/1.0',
                CURLOPT_HTTPHEADER => ['Range: bytes=0-'.($bytes - 1)],
            ]);
            $data = curl_exec($ch);
            curl_close($ch);
            return is_string($data) ? $data : '';
        }

        // Streams fallback
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'header' => "Range: bytes=0-".($bytes - 1)."\r\n",
            ]
        ]);
        $fp = @fopen($url, 'rb', false, $ctx);
        if ($fp === false) return '';
        $buf = '';
        while (!feof($fp) && strlen($buf) < $bytes) {
            $chunk = fread($fp, min(8192, $bytes - strlen($buf)));
            if ($chunk === false) break;
            $buf .= $chunk;
        }
        @fclose($fp);
        return $buf;
    }
}
