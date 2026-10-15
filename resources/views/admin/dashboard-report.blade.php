<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Operasi — {{ $dateLabel }}</title>
    <style>
        @page {
            margin: 26px 32px 54px 32px;
        }

        * {
            font-family: "DejaVu Sans", sans-serif;
        }

        body {
            margin: 0;
            color: #111c2d;
            font-size: 10px;
            line-height: 1.45;
        }

        .accent-bar {
            height: 5px;
            background: #b6005b;
            margin: -26px -32px 0 -32px;
        }

        .muted {
            color: #515f74;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            border-bottom: 1px solid #e2bdc5;
            padding-bottom: 12px;
        }

        .header td {
            vertical-align: top;
            padding-bottom: 12px;
        }

        .brand {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #111c2d;
        }

        .brand span {
            color: #b6005b;
        }

        .brand-sub {
            font-size: 8px;
            letter-spacing: 3px;
            color: #8e6f76;
            margin-top: 2px;
            text-transform: uppercase;
        }

        .doc-title {
            font-size: 15px;
            font-weight: bold;
            color: #111c2d;
            margin-top: 10px;
        }

        .doc-sub {
            font-size: 9px;
            color: #515f74;
            margin-top: 2px;
        }

        .header .right {
            text-align: right;
        }

        .date-pill {
            display: inline-block;
            background: #f0f3ff;
            border: 1px solid #e2bdc5;
            color: #b6005b;
            font-weight: bold;
            font-size: 10px;
            padding: 5px 10px;
        }

        .generated {
            margin-top: 6px;
            font-size: 8px;
            color: #8e6f76;
        }

        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #111c2d;
            margin: 20px 0 8px 0;
        }

        .section-title .bar {
            display: inline-block;
            width: 3px;
            height: 11px;
            background: #b6005b;
            margin-right: 6px;
            vertical-align: -1px;
        }

        .summary-cards {
            width: 100%;
            border-collapse: separate;
            border-spacing: 7px 0;
            margin-left: -7px;
            margin-right: -7px;
        }

        .summary-cards td {
            width: 16.66%;
            vertical-align: top;
        }

        .card {
            border: 1px solid #dee8ff;
            background: #f9f9ff;
            padding: 9px 10px;
            height: 62px;
        }

        .card .label {
            font-size: 7px;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: #515f74;
        }

        .card .value {
            font-size: 20px;
            font-weight: bold;
            color: #111c2d;
            margin-top: 4px;
            line-height: 1;
        }

        .card .value .unit {
            font-size: 8px;
            font-weight: normal;
            color: #515f74;
        }

        .card .foot {
            font-size: 7px;
            color: #8e6f76;
            margin-top: 5px;
            border-top: 1px solid #e7eeff;
            padding-top: 4px;
        }

        .card.accent {
            border-color: #b6005b;
            background: #fff5f9;
        }

        .card.accent .value {
            color: #b6005b;
        }

        .narrative {
            border-left: 3px solid #b6005b;
            background: #f0f3ff;
            padding: 11px 13px;
            font-size: 10px;
            color: #263143;
            line-height: 1.6;
        }

        .log {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        .log th {
            background: #111c2d;
            color: #ffffff;
            font-size: 8px;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            text-align: left;
            padding: 7px 8px;
        }

        .log td {
            padding: 7px 8px;
            font-size: 9px;
            border-bottom: 1px solid #e7eeff;
            vertical-align: top;
        }

        .log tr.alt td {
            background: #f7f9ff;
        }

        .resi {
            font-family: "DejaVu Sans Mono", monospace;
            font-weight: bold;
            color: #111c2d;
            font-size: 8.5px;
        }

        .time {
            color: #515f74;
            white-space: nowrap;
        }

        .chip {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8px;
            font-weight: bold;
            border-radius: 8px;
        }

        .arrow {
            color: #8e6f76;
            padding: 0 3px;
        }

        .empty {
            text-align: center;
            color: #8e6f76;
            padding: 16px;
            font-size: 9px;
        }

        .footer {
            position: fixed;
            bottom: -38px;
            left: 0;
            right: 0;
            border-top: 1px solid #e2bdc5;
            padding-top: 6px;
            font-size: 7.5px;
            color: #8e6f76;
        }

        .footer .right {
            text-align: right;
        }
    </style>
</head>
<body>
@php
    $toneColors = [
        'success' => ['#006b1c', '#e6f7ea'],
        'danger' => ['#ba1a1a', '#ffdad6'],
        'warning' => ['#b45309', '#fdf0d5'],
        'info' => ['#3a485c', '#d5e3fd'],
        'dark' => ['#263143', '#dee8ff'],
        'neutral' => ['#5a3f46', '#f0f3ff'],
        'muted' => ['#515f74', '#f0f3ff'],
    ];

    $num = fn (int|float $value): string => number_format((float) $value, 0, ',', '.');
    $pct = function (float $value): string {
        $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    };

    $cards = [
        ['label' => 'Total Pengiriman', 'value' => $num($summary['total_shipments']), 'unit' => 'resi', 'foot' => 'Utilisasi armada '.$pct($summary['fleet_capacity_pct']).'%', 'accent' => false],
        ['label' => 'Terkirim', 'value' => $num($summary['delivered_count']), 'unit' => 'resi', 'foot' => 'Rasio keberhasilan '.$pct($summary['success_rate_pct']).'%', 'accent' => false],
        ['label' => 'Dalam Proses', 'value' => $num($summary['in_process_count']), 'unit' => 'resi', 'foot' => 'Belum selesai hari ini', 'accent' => false],
        ['label' => 'Kendala', 'value' => $num($summary['issue_count']), 'unit' => 'kasus', 'foot' => 'Tingkat insiden '.$pct($summary['incident_rate_pct']).'%', 'accent' => true],
        ['label' => 'Menunggu Keputusan', 'value' => $num($summary['pending_decision_count']), 'unit' => 'kasus', 'foot' => $num($summary['decided_count']).' sudah diputuskan', 'accent' => true],
        ['label' => 'Dikembalikan', 'value' => $num($summary['returned_count']), 'unit' => 'resi', 'foot' => 'Ditarik ke hub', 'accent' => false],
    ];
@endphp

<div class="accent-bar"></div>

<table class="header">
    <tr>
        <td>
            <div class="brand">ANTERAJA <span>FROZEN</span></div>
            <div class="brand-sub">Rantai Dingin Terkendali</div>
            <div class="doc-title">Laporan Operasi Harian</div>
            <div class="doc-sub">Ringkasan otomatis &amp; riwayat aktivitas paket</div>
        </td>
        <td class="right">
            <span class="date-pill">{{ $dateLabel }}</span>
            <div class="generated">Dibuat {{ $generatedAt }}</div>
        </td>
    </tr>
</table>

<div class="section-title"><span class="bar"></span>Ringkasan Operasi</div>

<table class="summary-cards">
    <tr>
        @foreach ($cards as $card)
            <td>
                <div class="card{{ $card['accent'] ? ' accent' : '' }}">
                    <div class="label">{{ $card['label'] }}</div>
                    <div class="value">{{ $card['value'] }} <span class="unit">{{ $card['unit'] }}</span></div>
                    <div class="foot">{{ $card['foot'] }}</div>
                </div>
            </td>
        @endforeach
    </tr>
</table>

<div class="section-title"><span class="bar"></span>Ikhtisar Otomatis</div>
<div class="narrative">{{ $narrative }}</div>

<div class="section-title"><span class="bar"></span>Riwayat Aktivitas Paket</div>
<table class="log">
    <thead>
        <tr>
            <th style="width: 12%;">Waktu</th>
            <th style="width: 18%;">Resi</th>
            <th style="width: 20%;">Pembeli</th>
            <th style="width: 33%;">Perubahan Status</th>
            <th style="width: 17%;">Pelaku</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($activity as $index => $row)
            @php
                $tone = $row['tone'] ?? 'neutral';
                [$fg, $bg] = $toneColors[$tone] ?? $toneColors['neutral'];
            @endphp
            <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                <td class="time">{{ $row['at'] }}</td>
                <td><span class="resi">{{ $row['tracking'] }}</span></td>
                <td>{{ $row['buyer'] }}</td>
                <td>
                    @if ($row['fromLabel'])
                        <span class="muted">{{ $row['fromLabel'] }}</span>
                        <span class="arrow">&rsaquo;</span>
                    @endif
                    <span class="chip" style="color: {{ $fg }}; background: {{ $bg }};">{{ $row['toLabel'] }}</span>
                </td>
                <td>{{ $row['actor'] }}</td>
            </tr>
        @empty
            <tr>
                <td class="empty" colspan="5">Belum ada perubahan status pada tanggal ini.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    <table style="width: 100%;">
        <tr>
            <td>Anteraja Frozen — Laporan Operasi Harian {{ $dateLabel }}</td>
            <td class="right">{{ $generatedAt }}</td>
        </tr>
    </table>
</div>
</body>
</html>
