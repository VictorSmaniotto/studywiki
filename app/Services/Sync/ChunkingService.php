<?php

namespace App\Services\Sync;

class ChunkingService
{
    private const TOKEN_MAX = 512;

    private const TOKEN_TARGET = 400;

    private const OVERLAP_RATIO = 0.15;

    /**
     * Split a markdown body into heading-aware chunks.
     *
     * @return list<array{conteudo: string, heading_path: string|null, tokens: int}>
     */
    public function chunk(string $corpo): array
    {
        $sections = $this->splitBySections($corpo);
        $chunks = [];

        foreach ($sections as $section) {
            foreach ($this->splitSection($section['conteudo'], $section['heading_path']) as $chunk) {
                $chunks[] = $chunk;
            }
        }

        return array_values($chunks);
    }

    /**
     * @return list<array{heading_path: string|null, conteudo: string}>
     */
    private function splitBySections(string $corpo): array
    {
        $lines = explode("\n", $corpo);
        $sections = [];
        $headingStack = [];
        $currentContent = [];
        $currentHeadingPath = null;

        foreach ($lines as $line) {
            if (preg_match('/^(#{1,3})\s+(.+)/', $line, $m)) {
                $content = trim(implode("\n", $currentContent));
                if ($content !== '') {
                    $sections[] = ['heading_path' => $currentHeadingPath, 'conteudo' => $content];
                }

                $level = strlen($m[1]);
                $title = trim($m[2]);

                foreach (array_keys($headingStack) as $l) {
                    if ($l >= $level) {
                        unset($headingStack[$l]);
                    }
                }
                $headingStack[$level] = $title;
                ksort($headingStack);

                $currentHeadingPath = implode(' > ', $headingStack);
                $currentContent = [];
            } else {
                $currentContent[] = $line;
            }
        }

        $content = trim(implode("\n", $currentContent));
        if ($content !== '') {
            $sections[] = ['heading_path' => $currentHeadingPath, 'conteudo' => $content];
        }

        return $sections;
    }

    /**
     * @return list<array{conteudo: string, heading_path: string|null, tokens: int}>
     */
    private function splitSection(string $conteudo, ?string $headingPath): array
    {
        $tokens = $this->countTokens($conteudo);

        if ($tokens <= self::TOKEN_MAX) {
            return [['conteudo' => $conteudo, 'heading_path' => $headingPath, 'tokens' => $tokens]];
        }

        $paragraphs = array_values(array_filter(
            preg_split('/\n{2,}/', $conteudo),
            fn (string $p) => trim($p) !== ''
        ));

        // Expand paragraphs that individually exceed the limit into word-level units.
        $units = [];
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($this->countTokens($para) > self::TOKEN_MAX) {
                foreach ($this->splitByWords($para) as $unit) {
                    $units[] = $unit;
                }
            } else {
                $units[] = $para;
            }
        }

        $chunks = [];
        $currentUnits = [];
        $currentTokens = 0;
        $overlapText = '';

        foreach ($units as $unit) {
            $unitTokens = $this->countTokens($unit);

            if ($currentTokens + $unitTokens > self::TOKEN_MAX && $currentUnits) {
                $joined = implode("\n\n", $currentUnits);
                $chunkContent = $overlapText !== '' ? $overlapText.' '.$joined : $joined;
                $chunks[] = [
                    'conteudo' => trim($chunkContent),
                    'heading_path' => $headingPath,
                    'tokens' => $this->countTokens($chunkContent),
                ];
                $overlapText = $this->computeOverlap($joined);
                $currentUnits = [];
                $currentTokens = 0;
            }

            $currentUnits[] = $unit;
            $currentTokens += $unitTokens;
        }

        if ($currentUnits) {
            $joined = implode("\n\n", $currentUnits);
            $chunkContent = $overlapText !== '' ? $overlapText.' '.$joined : $joined;
            $chunks[] = [
                'conteudo' => trim($chunkContent),
                'heading_path' => $headingPath,
                'tokens' => $this->countTokens($chunkContent),
            ];
        }

        return $chunks ?: [['conteudo' => $conteudo, 'heading_path' => $headingPath, 'tokens' => $tokens]];
    }

    /**
     * Split a long paragraph into word groups each fitting within TOKEN_TARGET.
     *
     * @return string[]
     */
    private function splitByWords(string $text): array
    {
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $targetChars = self::TOKEN_TARGET * 4;
        $chunks = [];
        $currentWords = [];
        $currentChars = 0;

        foreach ($words as $word) {
            $wordChars = mb_strlen($word) + 1;
            if ($currentChars + $wordChars > $targetChars && $currentWords) {
                $chunks[] = implode(' ', $currentWords);
                $currentWords = [];
                $currentChars = 0;
            }
            $currentWords[] = $word;
            $currentChars += $wordChars;
        }

        if ($currentWords) {
            $chunks[] = implode(' ', $currentWords);
        }

        return $chunks ?: [$text];
    }

    private function computeOverlap(string $text): string
    {
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $count = (int) (count($words) * self::OVERLAP_RATIO);
        if ($count === 0) {
            return '';
        }

        return implode(' ', array_slice($words, -$count));
    }

    private function countTokens(string $text): int
    {
        return max(1, (int) (mb_strlen($text) / 4));
    }
}
