<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
    .header { padding: 16px 20px; border-bottom: 2px solid #3b82f6; margin-bottom: 16px; }
    .header h1 { font-size: 16px; font-weight: 700; color: #1e3a8a; }
    .header p { font-size: 10px; color: #6b7280; margin-top: 2px; }
    .summary { display: flex; gap: 12px; margin: 0 20px 16px; }
    .stat { flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; text-align: center; }
    .stat-label { font-size: 9px; color: #6b7280; text-transform: uppercase; }
    .stat-value { font-size: 18px; font-weight: 700; margin-top: 2px; }
    table { width: calc(100% - 40px); margin: 0 20px; border-collapse: collapse; }
    thead tr { background: #eff6ff; }
    th { padding: 7px 8px; text-align: left; font-size: 10px; color: #374151; font-weight: 600; border-bottom: 1px solid #bfdbfe; }
    th.center, td.center { text-align: center; }
    th.right, td.right { text-align: right; }
    td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; font-size: 10px; color: #374151; }
    tr:nth-child(even) td { background: #f9fafb; }
    .rate-green { color: #15803d; font-weight: 600; }
    .rate-yellow { color: #b45309; font-weight: 600; }
    .rate-red { color: #b91c1c; font-weight: 600; }
    .footer { margin-top: 16px; padding: 8px 20px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; text-align: right; }
</style>
</head>
<body>

<div class="header">
    <h1>BÁO CÁO CHẤM CÔNG</h1>
    <p>Tháng {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('m/Y') }} · Xuất lúc {{ now()->format('H:i d/m/Y') }}</p>
</div>

<div class="summary">
    <div class="stat">
        <div class="stat-label">Nhân viên</div>
        <div class="stat-value" style="color:#1e40af">{{ $report->count() }}</div>
    </div>
    <div class="stat">
        <div class="stat-label">Tỉ lệ TB</div>
        <div class="stat-value" style="color:#15803d">{{ $report->avg('rate') ? round($report->avg('rate'), 1) : 0 }}%</div>
    </div>
    <div class="stat">
        <div class="stat-label">Tổng vắng</div>
        <div class="stat-value" style="color:#b91c1c">{{ $report->sum('absent') }}</div>
    </div>
    <div class="stat">
        <div class="stat-label">Tổng trễ</div>
        <div class="stat-value" style="color:#b45309">{{ $report->sum('late') }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Nhân viên</th>
            <th>Mã NV</th>
            <th>Phòng ban</th>
            <th class="center">Đi làm</th>
            <th class="center">Vắng</th>
            <th class="center">Trễ</th>
            <th class="center">Về sớm</th>
            <th class="center">Tổng</th>
            <th class="right">Tỉ lệ</th>
        </tr>
    </thead>
    <tbody>
        @forelse($report as $row)
        @php
            $cls = $row['rate'] >= 90 ? 'rate-green' : ($row['rate'] >= 70 ? 'rate-yellow' : 'rate-red');
        @endphp
        <tr>
            <td>{{ $row['name'] }}</td>
            <td>{{ $row['code'] }}</td>
            <td>{{ $row['department'] }}</td>
            <td class="center">{{ $row['attended'] }}</td>
            <td class="center">{{ $row['absent'] }}</td>
            <td class="center">{{ $row['late'] }}</td>
            <td class="center">{{ $row['early_leave'] }}</td>
            <td class="center">{{ $row['total'] }}</td>
            <td class="right {{ $cls }}">{{ $row['rate'] }}%</td>
        </tr>
        @empty
        <tr><td colspan="9" style="text-align:center;color:#9ca3af;padding:20px">Không có dữ liệu</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">Hệ thống Chấm Công Tự Động · {{ config('app.name') }}</div>

</body>
</html>
