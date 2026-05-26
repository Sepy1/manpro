<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Statistik Server</title>
    <style>
        @page { margin: 10mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #0f172a;
        }
        .title {
            border: 1px solid #1e293b;
            padding: 8px;
            text-align: center;
            margin-bottom: 10px;
        }
        .title h1 {
            margin: 0 0 4px;
            font-size: 14px;
        }
        .title p {
            margin: 0;
            font-size: 10px;
        }
        .server-card {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 6px;
            margin-bottom: 7px;
            page-break-inside: avoid;
        }
        .server-title {
            margin: 0 0 5px;
            font-size: 11px;
            font-weight: 700;
        }
        .sensor-card {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 5px;
            margin-bottom: 5px;
            background: #ffffff;
        }
        .sensor-card:last-child {
            margin-bottom: 0;
        }
        .sensor-head {
            margin-bottom: 5px;
        }
        .sensor-title {
            font-size: 10px;
            font-weight: 700;
            margin: 0 0 2px;
        }
        .sensor-stats {
            margin: 0;
            color: #475569;
            font-size: 8px;
        }
        .legend {
            margin: 3px 0 0;
            font-size: 8px;
            color: #334155;
        }
        .legend span {
            margin-right: 8px;
        }
        .line-max { color: #dc2626; }
        .line-min { color: #2563eb; }
        .line-avg { color: #059669; }
        .chart-wrap {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 2px 4px 3px;
            background: #f8fafc;
        }
        .chart-caption {
            margin: 0 0 2px;
            font-size: 7.5px;
            color: #64748b;
        }
        .chart-image {
            width: 100%;
            height: auto;
            display: block;
        }
        .empty {
            border: 1px dashed #cbd5e1;
            color: #64748b;
            padding: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
@php
    $chartWidth = 680;
    $chartHeight = 145;
    $chartPadLeft = 48;
    $chartPadRight = 12;
    $chartPadTop = 10;
    $chartPadBottom = 24;

    $formatNumber = static function ($value, string $unit): string {
        if (! is_numeric($value)) {
            return '-';
        }

        if ($unit === 'B/s') {
            $number = (float) $value;
            if ($number >= 1000000000) {
                return number_format($number / 1000000000, 2) . ' GB/s';
            }
            if ($number >= 1000000) {
                return number_format($number / 1000000, 2) . ' MB/s';
            }
            if ($number >= 1000) {
                return number_format($number / 1000, 2) . ' KB/s';
            }
            return number_format($number, 2) . ' B/s';
        }

        return number_format((float) $value, 2) . ($unit !== '' ? ' ' . $unit : '');
    };

    $valueToY = static function (float $value, float $globalMin, float $globalMax) use ($chartHeight, $chartPadTop, $chartPadBottom): float {
        $usableHeight = $chartHeight - $chartPadTop - $chartPadBottom;
        $range = ($globalMax - $globalMin) > 0 ? ($globalMax - $globalMin) : 1.0;
        return $chartHeight - $chartPadBottom - (($value - $globalMin) / $range) * $usableHeight;
    };

    $buildPolyline = static function (array $values, float $globalMin, float $globalMax) use ($chartWidth, $chartPadLeft, $chartPadRight, $valueToY): string {
        if (count($values) === 0) {
            return '';
        }

        $usableWidth = $chartWidth - $chartPadLeft - $chartPadRight;
        $stepX = count($values) > 1 ? ($usableWidth / (count($values) - 1)) : 0.0;

        $points = [];
        foreach ($values as $index => $value) {
            $x = $chartPadLeft + ($stepX * $index);
            $y = $valueToY((float) $value, $globalMin, $globalMax);
            $points[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
        }

        return implode(' ', $points);
    };

    $buildAreaPath = static function (array $values, float $globalMin, float $globalMax) use ($chartWidth, $chartHeight, $chartPadLeft, $chartPadRight, $chartPadBottom, $valueToY): string {
        if (count($values) === 0) {
            return '';
        }

        $usableWidth = $chartWidth - $chartPadLeft - $chartPadRight;
        $stepX = count($values) > 1 ? ($usableWidth / (count($values) - 1)) : 0.0;
        $baseY = $chartHeight - $chartPadBottom;
        $firstX = $chartPadLeft;
        $lastX = $chartPadLeft + ($stepX * max(count($values) - 1, 0));

        $path = 'M ' . number_format($firstX, 2, '.', '') . ' ' . number_format($baseY, 2, '.', '');
        foreach ($values as $index => $value) {
            $x = $chartPadLeft + ($stepX * $index);
            $y = $valueToY((float) $value, $globalMin, $globalMax);
            $path .= ' L ' . number_format($x, 2, '.', '') . ' ' . number_format($y, 2, '.', '');
        }
        $path .= ' L ' . number_format($lastX, 2, '.', '') . ' ' . number_format($baseY, 2, '.', '') . ' Z';

        return $path;
    };

    $buildChartSvgDataUri = static function (array $labels, array $lineMin, array $lineMax, array $lineAvg, float $globalMin, float $globalMax, string $unit) use (
        $chartWidth,
        $chartHeight,
        $chartPadLeft,
        $chartPadRight,
        $chartPadTop,
        $chartPadBottom,
        $formatNumber,
        $buildPolyline,
        $buildAreaPath,
        $valueToY
    ): ?string {
        if (count($labels) <= 1) {
            return null;
        }

        $axisBottom = $chartHeight - $chartPadBottom;
        $usableWidth = $chartWidth - $chartPadLeft - $chartPadRight;
        $ticks = 5;

        $grid = '';
        for ($i = 0; $i < $ticks; $i++) {
            $ratio = $ticks === 1 ? 0 : ($i / ($ticks - 1));
            $tickValue = $globalMax - (($globalMax - $globalMin) * $ratio);
            $y = $valueToY((float) $tickValue, $globalMin, $globalMax);
            $grid .= '<line x1="'.$chartPadLeft.'" y1="'.number_format($y, 2, '.', '').'" x2="'.($chartWidth - $chartPadRight).'" y2="'.number_format($y, 2, '.', '').'" stroke="#dbeafe" stroke-width="1" />';
            $grid .= '<text x="'.($chartPadLeft - 4).'" y="'.number_format($y + 3, 2, '.', '').'" text-anchor="end" font-size="8" fill="#64748b">'.e($formatNumber($tickValue, $unit)).'</text>';
        }

        $xTicks = '';
        $labelCount = count($labels);
        $stepLabel = (int) ceil(max($labelCount, 1) / 6);
        for ($idx = 0; $idx < $labelCount; $idx++) {
            $x = $chartPadLeft + ($usableWidth * ($idx / max($labelCount - 1, 1)));
            $xFmt = number_format($x, 2, '.', '');
            $xTicks .= '<line x1="'.$xFmt.'" y1="'.$chartPadTop.'" x2="'.$xFmt.'" y2="'.$axisBottom.'" stroke="#e2e8f0" stroke-width="0.7" />';
            $xTicks .= '<line x1="'.$xFmt.'" y1="'.$axisBottom.'" x2="'.$xFmt.'" y2="'.($axisBottom + 3).'" stroke="#94a3b8" stroke-width="1" />';
            if ($idx % max($stepLabel, 1) === 0 || $idx === $labelCount - 1) {
                $labelEscaped = e((string) ($labels[$idx] ?? ''));
                $xTicks .= '<text x="'.$xFmt.'" y="'.($chartHeight - 5).'" text-anchor="middle" font-size="6.6" fill="#64748b">'.$labelEscaped.'</text>';
            }
        }

        $nodes = '';
        for ($idx = 0; $idx < $labelCount; $idx++) {
            $x = $chartPadLeft + ($usableWidth * ($idx / max($labelCount - 1, 1)));
            $xFmt = number_format($x, 2, '.', '');
            if (isset($lineMax[$idx]) && is_numeric($lineMax[$idx])) {
                $nodes .= '<circle cx="'.$xFmt.'" cy="'.number_format($valueToY((float) $lineMax[$idx], $globalMin, $globalMax), 2, '.', '').'" r="1.8" fill="#dc2626" />';
            }
            if (isset($lineMin[$idx]) && is_numeric($lineMin[$idx])) {
                $nodes .= '<circle cx="'.$xFmt.'" cy="'.number_format($valueToY((float) $lineMin[$idx], $globalMin, $globalMax), 2, '.', '').'" r="1.8" fill="#2563eb" />';
            }
            if (isset($lineAvg[$idx]) && is_numeric($lineAvg[$idx])) {
                $nodes .= '<circle cx="'.$xFmt.'" cy="'.number_format($valueToY((float) $lineAvg[$idx], $globalMin, $globalMax), 2, '.', '').'" r="1.9" fill="#059669" />';
            }
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$chartWidth.'" height="'.$chartHeight.'" viewBox="0 0 '.$chartWidth.' '.$chartHeight.'">'
            .'<rect x="0" y="0" width="'.$chartWidth.'" height="'.$chartHeight.'" fill="#f8fafc" />'
            .$grid
            .'<line x1="'.$chartPadLeft.'" y1="'.$chartPadTop.'" x2="'.$chartPadLeft.'" y2="'.$axisBottom.'" stroke="#94a3b8" stroke-width="1" />'
            .'<line x1="'.$chartPadLeft.'" y1="'.$axisBottom.'" x2="'.($chartWidth - $chartPadRight).'" y2="'.$axisBottom.'" stroke="#94a3b8" stroke-width="1" />'
            .'<path d="'.$buildAreaPath($lineAvg, $globalMin, $globalMax).'" fill="#059669" fill-opacity="0.10" />'
            .'<polyline fill="none" stroke="#dc2626" stroke-width="2" points="'.$buildPolyline($lineMax, $globalMin, $globalMax).'" />'
            .'<polyline fill="none" stroke="#2563eb" stroke-width="2" points="'.$buildPolyline($lineMin, $globalMin, $globalMax).'" />'
            .'<polyline fill="none" stroke="#059669" stroke-width="2.4" points="'.$buildPolyline($lineAvg, $globalMin, $globalMax).'" />'
            .$nodes
            .$xTicks
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    };
@endphp

<div class="title">
    <h1>Laporan Statistik Server PRTG</h1>
    <p>Periode: {{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}</p>
    <p>Dibuat: {{ $generatedAt->format('d M Y H:i:s') }}</p>
</div>

@if (empty($servers))
    <div class="empty">Tidak ada data sensor pada periode ini.</div>
@else
    @foreach ($servers as $server)
        <div class="server-card">
            <p class="server-title">{{ $server['server'] }}</p>

            @foreach ($server['sensors'] as $sensor)
                @php
                    $labels = $sensor['trend']['labels'] ?? [];
                    $lineMin = $sensor['trend']['min'] ?? [];
                    $lineMax = $sensor['trend']['max'] ?? [];
                    $lineAvg = $sensor['trend']['avg'] ?? [];
                    $allValues = array_values(array_filter(array_merge($lineMin, $lineMax, $lineAvg), static fn ($v) => is_numeric($v)));
                    $globalMin = ! empty($allValues) ? (float) min($allValues) : 0.0;
                    $globalMax = ! empty($allValues) ? (float) max($allValues) : 1.0;
                @endphp

                <div class="sensor-card">
                    <div class="sensor-head">
                        <p class="sensor-title">{{ $sensor['label'] }}</p>
                        <p class="sensor-stats">
                            Min: {{ $formatNumber($sensor['stats']['min'] ?? null, $sensor['unit']) }} |
                            Max: {{ $formatNumber($sensor['stats']['max'] ?? null, $sensor['unit']) }} |
                            Avg: {{ $formatNumber($sensor['stats']['avg'] ?? null, $sensor['unit']) }}
                        </p>
                    </div>

                    @if (count($labels) <= 1)
                        <div class="empty">Data grafik tidak mencukupi.</div>
                    @else
                        @php
                            $chartDataUri = $buildChartSvgDataUri($labels, $lineMin, $lineMax, $lineAvg, $globalMin, $globalMax, (string) ($sensor['unit'] ?? ''));
                        @endphp
                        <div class="chart-wrap">
                            <p class="chart-caption">Trend sensor per waktu (mirip grafik traffic dashboard data center)</p>
                            @if ($chartDataUri)
                                <img src="{{ $chartDataUri }}" alt="Chart {{ $sensor['label'] }}" class="chart-image">
                            @else
                                <div class="empty">Data grafik tidak mencukupi.</div>
                            @endif
                        </div>

                        <p class="legend">
                            <span class="line-max">■ Maksimal</span>
                            <span class="line-min">■ Minimal</span>
                            <span class="line-avg">■ Average</span>
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
@endif
</body>
</html>
