<?php

namespace BoldMinded\DexterCore\Service;

class StopWordRemover
{
    public function remove(string $text, array $stopWords): string
    {
        // Normalize text
        $text = strtolower($text);
        $words = preg_split('/\W+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        $filtered = array_filter($words, function ($word) use ($stopWords) {
            return !in_array($word, $stopWords);
        });

        return implode(' ', $filtered);
    }
}
