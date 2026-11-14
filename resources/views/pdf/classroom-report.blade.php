<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Class report — {{ $classroom->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; padding: 28px; }
        .header { margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #0d9488; }
        .header h1 { font-size: 18px; color: #0d9488; }
        .header .sub { font-size: 10.5px; color: #6b7280; margin-top: 4px; }
        .summary { margin-bottom: 14px; font-size: 11px; }
        .summary strong { color: #0d9488; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: center; }
        th { background: #f0fdfa; font-size: 10px; color: #0f766e; }
        td.name, th.name { text-align: left; }
        .pass { color: #059669; font-weight: 700; }
        .warn { color: #d97706; font-weight: 700; }
        .fail { color: #dc2626; font-weight: 700; }
        .muted { color: #9ca3af; }
        tfoot td { background: #f9fafb; font-weight: 700; }
        .footer { margin-top: 20px; font-size: 9.5px; color: #9ca3af; }
    </style>
</head>
<body>
    @php
        $cls = fn ($score) => $score === null ? 'muted' : ($score >= 70 ? 'pass' : ($score >= 50 ? 'warn' : 'fail'));
    @endphp

    <div class="header">
        <h1>Class report — {{ $classroom->name }}</h1>
        <div class="sub">
            Teacher: {{ $classroom->teacher->name }}
            @if ($classroom->academic_year) · {{ $classroom->academic_year }} @endif
            · Generated {{ now()->format('d M Y, H:i') }}
        </div>
    </div>

    <div class="summary">
        Students: <strong>{{ count($progress['rows']) }}</strong> ·
        Exams: <strong>{{ $progress['assignments']->count() }}</strong> ·
        Class average: <strong>{{ $progress['class_average'] !== null ? $progress['class_average'].'%' : '—' }}</strong>
        <span class="muted"> (best score per student and exam)</span>
    </div>

    <table>
        <thead>
            <tr>
                <th class="name">Student</th>
                @foreach ($progress['assignments'] as $assignment)
                    <th>
                        {{ $assignment->exam->title }}
                        @if ($assignment->subject)<br><span class="muted">{{ $assignment->subject->name }}</span>@endif
                    </th>
                @endforeach
                <th>Average</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($progress['rows'] as $row)
                <tr>
                    <td class="name">{{ $row['student']->name }}</td>
                    @foreach ($progress['assignments'] as $assignment)
                        @php $cell = $row['cells'][$assignment->id]; @endphp
                        <td class="{{ $cls($cell['best']) }}">
                            @if ($cell['status'] === 'completed')
                                {{ $cell['best'] }}%
                            @elseif ($cell['status'] === 'in_progress')
                                <span class="muted">In progress</span>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    @endforeach
                    <td class="{{ $cls($row['average']) }}">{{ $row['average'] !== null ? $row['average'].'%' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $progress['assignments']->count() + 2 }}" class="muted">No students have joined this class yet.</td></tr>
            @endforelse
        </tbody>
        @if ($progress['assignments']->isNotEmpty())
            <tfoot>
                <tr>
                    <td class="name">Average / pass rate</td>
                    @foreach ($progress['assignments'] as $assignment)
                        @php $st = $progress['exam_stats'][$assignment->id]; @endphp
                        <td>
                            {{ $st['average'] !== null ? $st['average'].'%' : '—' }}
                            <br><span class="muted">{{ $st['pass_rate'] !== null ? $st['pass_rate'].'% pass' : '' }}</span>
                        </td>
                    @endforeach
                    <td>{{ $progress['class_average'] !== null ? $progress['class_average'].'%' : '—' }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer">{{ config('app.name') }} — pass mark: 50%</div>
</body>
</html>
