<?php

namespace App\Support;

use App\Models\Division;
use Illuminate\Support\Collection;

/**
 * Ekstraksi divisi dari teks:
 * — @NamaDivisi (kompatibel mundur),
 * — dan nama divisi yang tertulis tegas dalam teks (batas kata / bukan substring di dalam email @).
 */
final class DivisionMentionParser
{
    /**
     * @param  Collection<int, Division>  $divisionsPool
     * @return array{division_ids:int[],unknown_mentions:string[]}
     */
    public function parse(string $text, Collection $divisionsPool): array
    {
        $normalized = preg_replace('/\r\n/', "\n", $text) ?? '';
        if ($normalized === '') {
            return ['division_ids' => [], 'unknown_mentions' => []];
        }

        $sorted = $divisionsPool
            ->filter(fn (Division $d) => mb_strlen(trim($d->name)) > 0)
            ->sortByDesc(fn (Division $d) => mb_strlen($d->name))
            ->values();

        $ranges = [];
        [$atIds, $unknown] = $this->collectFromAtMentions($normalized, $sorted, $ranges);

        $plainIds = $this->collectPlainNames($normalized, $sorted, $ranges);

        return [
            'division_ids' => array_values(array_unique(array_map('intval', array_merge($atIds, $plainIds)))),
            'unknown_mentions' => $unknown,
        ];
    }

    /**
     * @param  array<int, array{s:int, e:int}>  $ranges
     * @return array{0: int[], 1: string[]}
     */
    private function collectFromAtMentions(string $text, Collection $sorted, array &$ranges): array
    {
        $divisionIds = [];
        $unknownStrings = [];

        $len = mb_strlen($text);
        $i = 0;
        while ($i < $len) {
            if (mb_substr($text, $i, 1) !== '@') {
                $i++;

                continue;
            }

            $atMbIndex = $i;
            if ($this->isWordContinueLeft($text, $atMbIndex)) {
                $i++;

                continue;
            }

            $afterAt = $atMbIndex + 1;
            $remainder = mb_substr($text, $afterAt);

            $matched = false;
            foreach ($sorted as $div) {
                $name = $div->name;
                $nameLen = mb_strlen($name);
                if ($nameLen === 0) {
                    continue;
                }
                $prefix = mb_substr($remainder, 0, $nameLen);
                if (! $this->ciEqual($prefix, $name)) {
                    continue;
                }

                $afterName = mb_substr($remainder, $nameLen, 1);
                if ($afterName !== '' && $this->isWordContinueRight($afterName)) {
                    continue;
                }

                $divisionIds[] = (int) $div->id;
                $ranges[] = ['s' => $atMbIndex, 'e' => $afterAt + $nameLen];
                $i = $afterAt + $nameLen;
                $matched = true;

                break;
            }

            if ($matched) {
                continue;
            }

            if (preg_match('/^([^@\s]+)/u', $remainder, $m) === 1) {
                $unknownStrings[] = $m[1];
                $consumeLen = mb_strlen($m[1]);
                $ranges[] = ['s' => $atMbIndex, 'e' => $afterAt + $consumeLen];
                $i = $afterAt + $consumeLen;

                continue;
            }

            $ranges[] = ['s' => $atMbIndex, 'e' => $afterAt];
            $i++;
        }

        return [
            $divisionIds,
            array_values(array_unique($unknownStrings)),
        ];
    }

    /**
     * @param  array<int, array{s:int, e:int}>  $occupied
     * @return int[]
     */
    private function collectPlainNames(string $text, Collection $sorted, array $occupied): array
    {
        $hayLen = mb_strlen($text);
        /** @var array<int, array{s:int,e:int,id:int}> $candidates */
        $candidates = [];

        foreach ($sorted as $div) {
            $name = $div->name;
            $nameLen = mb_strlen($name);
            if ($nameLen === 0 || $hayLen < $nameLen) {
                continue;
            }

            $offset = 0;
            while ($offset <= $hayLen - $nameLen) {
                $pos = mb_stripos($text, $name, $offset, 'UTF-8');
                if ($pos === false) {
                    break;
                }
                $end = $pos + $nameLen;

                $beforeOk = ! $this->isWordContinueLeft($text, $pos) && mb_substr($text, max(0, $pos - 1), 1) !== '@';
                $afterCh = $end < $hayLen ? mb_substr($text, $end, 1) : '';
                $afterOk = $afterCh === '' || ! $this->isWordContinueRight($afterCh);
                $free = ! $this->rangesOverlapAny($occupied, $pos, $end);

                if ($beforeOk && $afterOk && $free) {
                    $candidates[] = ['s' => $pos, 'e' => $end, 'id' => (int) $div->id];
                }

                $offset = $pos + 1;
            }
        }

        usort($candidates, fn (array $a, array $b) => $a['s'] <=> $b['s']
            ?: ($b['e'] - $b['s']) <=> ($a['e'] - $a['s'])
        );

        $scan = $occupied;
        $pickedIds = [];
        foreach ($candidates as $c) {
            if ($this->rangesOverlapAny($scan, $c['s'], $c['e'])) {
                continue;
            }
            $scan[] = ['s' => $c['s'], 'e' => $c['e']];
            $pickedIds[] = $c['id'];
        }

        return array_values(array_unique($pickedIds));
    }

    private function rangesOverlapAny(array $ranges, int $s, int $e): bool
    {
        foreach ($ranges as $r) {
            if ($s < $r['e'] && $e > $r['s']) {
                return true;
            }
        }

        return false;
    }

    /** Ada huruf / angka / _ / hyphen langsung sebelum posisi (sambungan kata). */
    private function isWordContinueLeft(string $text, int $mbIndex): bool
    {
        if ($mbIndex <= 0) {
            return false;
        }

        return $this->isWordContinueRight(mb_substr($text, $mbIndex - 1, 1));
    }

    private function isWordContinueRight(string $singleChar): bool
    {
        return preg_match('/^[\p{L}\p{N}_\-]$/u', $singleChar) === 1;
    }

    private function ciEqual(string $a, string $b): bool
    {
        return mb_strtolower($a) === mb_strtolower($b);
    }
}
