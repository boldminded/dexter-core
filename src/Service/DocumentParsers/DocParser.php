<?php

namespace Litzinger\DexterCore\Service\DocumentParsers;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class DocParser extends AbstractDocumentParser
{
    public function parse(string $filePath, array $options = []): string
    {
        $parseOptions = $this->getParseOptions($options);

        try {
            $phpWord = $this->loadWordDocument($filePath);
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . "\n";
                    } elseif (method_exists($element, 'getElements')) {
                        $text .= $this->extractTextFromElements($element->getElements()) . "\n";
                    }

                    // Check word limit during processing
                    if ($parseOptions->maxWords !== null) {
                        $wordCount = str_word_count($text);
                        if ($wordCount >= $parseOptions->maxWords) {
                            break 2;
                        }
                    }
                }
            }

            return $this->applyLimits(trim($text), $parseOptions);
        } catch (\Exception $e) {
            throw new \RuntimeException("Error parsing DOC file: " . $e->getMessage());
        }
    }

    private function extractTextFromElements($elements): string
    {
        $text = '';
        foreach ($elements as $element) {
            if (method_exists($element, 'getText')) {
                $text .= $element->getText() . ' ';
            } elseif (method_exists($element, 'getElements')) {
                $text .= $this->extractTextFromElements($element->getElements()) . ' ';
            }
        }
        return $text;
    }

    public function getSupportedMimeTypes(): array
    {
        return [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
    }

    private function loadWordDocument(string $filePath): PhpWord
    {
        $localPath = $filePath;
        $isUrl = str_starts_with($filePath, 'http://') || str_starts_with($filePath, 'https://');

        if ($isUrl) {
            $localPath = $this->downloadToTemp($filePath);
        }

        if (!is_file($localPath) || !is_readable($localPath)) {
            throw new \RuntimeException("File not found or unreadable: {$localPath}");
        }

        $size = filesize($localPath);

        if ($size === 0) {
            throw new \RuntimeException("File is empty: {$localPath}");
        }

        // 2) Peek the first few bytes to distinguish real docx/odt zip vs rtf/html etc.
        $fp = fopen($localPath, 'rb');
        $head = fread($fp, 8) ?: '';
        fclose($fp);

        $isZipLike = strncmp($head, "PK\x03\x04", 4) === 0;
        $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));

        // Choose reader explicitly to avoid mis-detection
        $readerName = null;
        if ($isZipLike && ($ext === 'docx' || $ext === 'zip')) {
            $readerName = 'Word2007';
        } elseif ($isZipLike && $ext === 'odt') {
            $readerName = 'ODText';
        } elseif ($ext === 'rtf') {
            $readerName = 'RTF';
        } elseif ($ext === 'htm' || $ext === 'html') {
            $readerName = 'HTML';
        } else {
            // If it looks like zip but extension is odd, try Word2007 anyway
            if ($isZipLike) {
                $readerName = 'Word2007';
            }
        }

        if ($readerName === null) {
            throw new \RuntimeException(
                "Unsupported or unknown format for '{$localPath}' (ext: .{$ext}). ".
                "PhpWord can read .docx/.odt/.rtf/.html — not .doc."
            );
        }

        try {
            $reader = IOFactory::load($readerName);
            return $reader->load($localPath);
        } catch (\Throwable $e) {
            // Improve the message when it’s actually a non-zip “.docx”
            if ($readerName === 'Word2007' && !$isZipLike) {
                throw new \RuntimeException(
                    "The file has .docx extension but is not a valid OOXML zip archive (no PK header).", 0, $e
                );
            }
            throw new \RuntimeException("PhpWord failed loading {$readerName}: ".$e->getMessage(), 0, $e);
        } finally {
            if ($isUrl && is_file($localPath)) {
                @unlink($localPath);
            }
        }
    }

    private function downloadToTemp(string $url): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'doc_');
        $fh  = @fopen($tmp, 'wb');

        if ($fh === false) {
            throw new \RuntimeException("Unable to create temp file for download.");
        }

        // Use cURL if available for redirects/range; otherwise streams
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_USERAGENT      => 'DexterDocLoader/1.0',
            ]);
            $data = curl_exec($ch);
            $err  = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($data === false || $code >= 400) {
                @fclose($fh);
                @unlink($tmp);
                throw new \RuntimeException("Download failed ({$code}): {$err}");
            }
            fwrite($fh, $data);
        } else {
            $ctx = stream_context_create([
                'http' => ['timeout' => 30, 'follow_location' => 1, 'max_redirects' => 5]
            ]);

            $in = @fopen($url, 'rb', false, $ctx);

            if ($in === false) {
                @fclose($fh);
                @unlink($tmp);
                throw new \RuntimeException("Unable to open remote URL: {$url}");
            }

            stream_copy_to_stream($in, $fh);
            @fclose($in);
        }

        @fclose($fh);
        return $tmp;
    }
}
