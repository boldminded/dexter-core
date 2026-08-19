<?php

namespace BoldMinded\DexterCore\Service;

use BoldMinded\DexterCore\Contracts\ConfigInterface;
use BoldMinded\DexterCore\Contracts\IndexableFileInterface;

/**
 * Resolves the address an AI provider is given for an image.
 *
 * Image description works by handing the provider a URL that it fetches
 * itself, which fails for any site it cannot reach from the internet --
 * local development, staging behind auth, an intranet. In those cases the
 * image is inlined as a data URI instead, so describing works without the
 * site being publicly addressable.
 *
 * Only images are inlined. Documents are parsed locally and only their
 * extracted text is sent, so their URL is never fetched remotely.
 */
class ImageInliner
{
    public const CONFIG_KEY = 'maxInlineImageBytes';

    /**
     * Providers cap request bodies and base64 inflates by about a third, so a
     * large original would be rejected after the cost of reading and encoding.
     */
    public const DEFAULT_MAX_BYTES = 15 * 1024 * 1024;

    public function __construct(private ?ConfigInterface $config = null)
    {
    }

    /**
     * The URL for $file, replaced by a data URI when it is an image the
     * provider could not fetch for itself.
     */
    public function resolveUrl(IndexableFileInterface $file): string
    {
        $url = $file->getAbsoluteUrl();

        if (!$file->isImage() || HostReachability::isPublic($url, $this->config)) {
            return $url;
        }

        // Fall back to the original URL: unreachable is still better than
        // empty, and the caller may know something we do not.
        return $this->toDataUri($file) ?: $url;
    }

    /**
     * The image as a data URI, or an empty string when it cannot be read or is
     * too large. Assets on remote disks (S3 and similar) have no readable local
     * path, so this is expected to fail for them rather than being an error.
     */
    public function toDataUri(IndexableFileInterface $file): string
    {
        $path = $file->getAbsolutePath();

        if ($path === '' || !is_readable($path)) {
            return '';
        }

        $size = @filesize($path);

        if ($size === false || $size > $this->maxBytes()) {
            return '';
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return '';
        }

        return 'data:' . ($file->getMimeType() ?: 'image/jpeg')
            . ';base64,' . base64_encode($contents);
    }

    private function maxBytes(): int
    {
        $configured = $this->config?->get(self::CONFIG_KEY);

        if (!is_numeric($configured) || (int) $configured <= 0) {
            return self::DEFAULT_MAX_BYTES;
        }

        return (int) $configured;
    }
}
