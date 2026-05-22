<?php

namespace App\Support;

use App\Models\ExternCr;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ExternCrNomorGenerator
{
    /**
     * @return array{0: string, 1: positive-int} [nomor, daily_sequence]
     */
    public function nextForDate(string|\DateTimeInterface $tanggal): array
    {
        $day = CarbonImmutable::parse($tanggal)->startOfDay();

        return DB::transaction(function () use ($day): array {
            $dateString = $day->toDateString();
            /** @var int|null $max */
            $max = ExternCr::query()
                ->whereDate('tanggal', '=', $dateString)
                ->lockForUpdate()
                ->max('daily_sequence');

            $seq = ($max ?? 0) + 1;
            $nomor = $day->format('Ymd').'-'.sprintf('%03d', $seq);

            return [$nomor, $seq];
        });
    }
}
