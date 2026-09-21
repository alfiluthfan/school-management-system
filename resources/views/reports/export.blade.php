<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 30px 28px 36px; }
        body { font-family: DejaVu Sans, sans-serif; color: #172b4d; font-size: 9px; }
        h1 { font-size: 17px; margin: 0 0 8px; color: #133b64; }
        h2 { font-size: 11px; color: #133b64; margin: 18px 0 7px; }
        .info { background: #edf3f8; padding: 9px; line-height: 1.6; }
        .note { margin: 12px 0; color: #48596b; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #173e67; color: #fff; text-align: left; font-size: 8px; }
        td, th { border: 1px solid #d8e2ed; padding: 5px; word-wrap: break-word; }
        tbody tr:nth-child(even) { background: #f3f7fb; }
        .footer { margin-top: 20px; color: #687a8b; font-size: 8px; }
    </style>
</head>
<body>
<h1>{{ $document['title'] }}</h1>
<div class="info">
    Periode: {{ $document['period'] }}<br>
    Dibuat: {{ $document['generated_at'] }}<br>
    Pemohon: {{ $document['requester'] }}<br>
    Filter: {{ $document['filters'] }}<br>
    Referensi ekspor: {{ $document['uuid'] }}
</div>
<p class="note">{{ $document['note'] }}</p>
@foreach ($document['sections'] as $section)
    <h2>{{ $section['title'] }}</h2>
    <table>
        <thead><tr>
            @foreach ($section['headers'] as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr></thead>
        <tbody>
        @forelse ($section['rows'] as $row)
            <tr>
                @foreach ($row as $cell)
                    <td>{{ (string) ($cell ?? '') }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($section['headers']) }}">Tidak ada data</td></tr>
        @endforelse
        </tbody>
    </table>
@endforeach
<div class="footer">School Management System · Dokumen laporan terbatas · Diambil dari data pada waktu pembuatan.</div>
</body>
</html>
