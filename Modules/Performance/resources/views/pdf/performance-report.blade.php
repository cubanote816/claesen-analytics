<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Performance Report - {{ $employee->name }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #1f2937; line-height: 1.5; }
        .header { text-align: left; border-bottom: 2px solid #3b82f6; padding-bottom: 20px; display: table; width: 100%; }
        .company-info { display: table-cell; vertical-align: middle; }
        .report-title { display: table-cell; text-align: right; vertical-align: middle; color: #6b7280; font-size: 10px; letter-spacing: 1px; }
        .logo { font-size: 24px; font-weight: bold; color: #1e3a8a; margin-bottom: 4px; }
        
        .section-title { font-size: 14px; font-weight: bold; margin-top: 30px; margin-bottom: 10px; border-left: 4px solid #3b82f6; padding-left: 10px; color: #111827; background: #f3f4f6; padding-top: 5px; padding-bottom: 5px; }
        
        .metric-grid { width: 100%; margin-top: 20px; border-collapse: separate; border-spacing: 10px; }
        .metric-box { padding: 15px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; text-align: center; }
        .metric-value { font-size: 18px; font-weight: bold; color: #2563eb; }
        .metric-label { font-size: 9px; color: #6b7280; text-transform: uppercase; margin-top: 4px; font-weight: 600; }
        
        table.data-table { width: 100%; margin-top: 15px; border-collapse: collapse; border: 1px solid #e5e7eb; }
        table.data-table th { background: #f9fafb; padding: 10px; text-align: left; border-bottom: 2px solid #e5e7eb; font-size: 11px; color: #374151; }
        table.data-table td { padding: 10px; border-bottom: 1px solid #f3f4f6; font-size: 11px; }
        
        .labor-tag { font-size: 9px; padding: 2px 6px; background: #eff6ff; color: #1e40af; border-radius: 4px; margin-right: 4px; display: inline-block; }
        
        .signature-section { margin-top: 100px; width: 100%; }
        .signature-area { width: 45%; display: inline-block; vertical-align: top; }
        .signature-line { border-top: 1px solid #9ca3af; margin-top: 40px; padding-top: 8px; font-size: 10px; color: #4b5563; }
        
        .footer { position: fixed; bottom: 0; width: 100%; font-size: 9px; color: #9ca3af; text-align: center; padding: 10px 0; border-top: 1px solid #f3f4f6; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <div class="logo">CLAESEN VERLICHTING</div>
            <div style="font-size: 10px;">Industrieterrein "Den Hoek" | Belgium</div>
        </div>
        <div class="report-title">
            EMPLOYEE PERFORMANCE AUDIT (AI)<br>
            CODE: CV-PERF-{{ $employee->id }}-{{ date('Y') }}
        </div>
    </div>

    <div style="margin-top: 25px;">
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%;">
                    <span style="color: #6b7280; font-size: 10px;">EMPLOYEE:</span><br>
                    <span style="font-size: 16px; font-weight: bold;">{{ $employee->name }}</span><br>
                    <span style="color: #6b7280;">{{ $employee->function ?? 'Technician' }}</span>
                </td>
                <td style="text-align: right; vertical-align: bottom;">
                    <span style="color: #6b7280; font-size: 10px;">AUDIT DATE:</span> {{ $generated_at }}
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">AI PERFORMANCE INSIGHTS & TEAM POSITIONING</div>
    <table class="metric-grid">
        <tr>
            <td class="metric-box">
                <div class="metric-value">{{ $ranking['rank'] }}</div>
                <div class="metric-label">Team Position</div>
            </td>
            <td class="metric-box">
                <div class="metric-value">{{ $ranking['percentile'] }}%</div>
                <div class="metric-label">Efficiency Percentile</div>
            </td>
            <td class="metric-box">
                <div class="metric-value" style="font-size: 14px;">{{ $ranking['label'] }}</div>
                <div class="metric-label">Performance Status</div>
            </td>
            <td class="metric-box">
                <div class="metric-value">{{ $profile['burnout_risk_score'] ?? 0 }}%</div>
                <div class="metric-label">Burnout Risk Score</div>
            </td>
        </tr>
    </table>

    <div class="section-title">WEEKLY PROJECT BREAKDOWN</div>
    <p style="font-size: 10px; color: #4b5563;">Current Week Hours: <strong>{{ $weekly['hours'] }}h</strong> / Achievement: <strong>{{ round($weekly['achievement_rate'], 1) }}%</strong></p>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 40%;">Project Name</th>
                <th style="width: 15%;">Hours</th>
                <th style="width: 45%;">Labor Composition (Detailed)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($weekly['projects'] as $project)
            <tr>
                <td style="font-weight: bold;">{{ $project['project_name'] }}</td>
                <td style="color: #2563eb; font-weight: bold;">{{ number_format($project['total_hours'], 1) }} h</td>
                <td>
                    @foreach($project['labor_breakdown'] as $lb)
                        <span class="labor-tag">{{ $lb['type'] }}: {{ $lb['hours'] }}h</span>
                    @endforeach
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">MONTHLY HISTORICAL CONTEXT</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 70%;">Project Name</th>
                <th style="width: 30%; text-align: right;">Total Cumulative Hours</th>
            </tr>
        </thead>
        <tbody>
            @foreach($monthly['projects'] as $project)
            <tr>
                <td>{{ $project['project_name'] }}</td>
                <td style="text-align: right;">{{ number_format($project['total_hours'], 1) }} h</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="signature-section">
        <div class="signature-area">
            <div class="signature-line">
                Employee Acknowledgement<br>
                <strong>Date:</strong> ________________
            </div>
        </div>
        <div class="signature-area" style="float: right;">
            <div class="signature-line">
                Audit Verified by (Manager/HR)<br>
                <strong>Name:</strong> ________________
            </div>
        </div>
    </div>

    <div class="footer">
        CONFIDENTIAL - Claesen Verlichting Internal Operations Hub - Generated by CAFCA Intelligence
    </div>
</body>
</html>
