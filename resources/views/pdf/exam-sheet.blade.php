<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>{{ $exam->title }}{{ $withAnswers ? ' — Answer key' : '' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; padding: 32px; }
        .header { margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #0d9488; }
        .header h1 { font-size: 20px; color: #0d9488; }
        .header .sub { font-size: 11px; color: #6b7280; margin-top: 4px; }
        .key-badge { display: inline-block; margin-top: 6px; padding: 2px 10px; background: #fef3c7; color: #92400e; border-radius: 999px; font-size: 10px; font-weight: 700; }
        .student-line { margin: 14px 0 18px; font-size: 11px; color: #374151; }
        .student-line span { display: inline-block; border-bottom: 1px solid #9ca3af; min-width: 200px; }
        .question { margin-bottom: 16px; page-break-inside: avoid; }
        .q-text { font-weight: 700; font-size: 12px; }
        .option { margin: 4px 0 0 18px; font-size: 11.5px; }
        .option.correct { color: #059669; font-weight: 700; }
        .lines { margin-top: 8px; }
        .lines div { border-bottom: 1px solid #d1d5db; height: 20px; }
        .answer { margin: 6px 0 0 18px; font-size: 11.5px; color: #059669; font-weight: 700; }
        .footer { margin-top: 28px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $exam->title }}</h1>
        <div class="sub">
            {{ $exam->questions->count() }} questions
            @if ($exam->time_limit) · {{ $exam->time_limit }} min @endif
        </div>
        @if ($withAnswers)
            <span class="key-badge">ANSWER KEY — TEACHER COPY</span>
        @endif
    </div>

    @if (! $withAnswers)
        <div class="student-line">Name: <span>&nbsp;</span> &nbsp;&nbsp; Date: <span style="min-width:100px">&nbsp;</span></div>
    @endif

    @foreach ($exam->questions as $index => $question)
        <div class="question">
            <div class="q-text">{{ $index + 1 }}. {{ $question->question }}</div>

            @if (! empty($question->options))
                @foreach ($question->options as $option)
                    <div class="option {{ $withAnswers && strtolower(trim((string) $option)) === strtolower(trim((string) $question->correct_answer)) ? 'correct' : '' }}">
                        {{ $withAnswers && strtolower(trim((string) $option)) === strtolower(trim((string) $question->correct_answer)) ? '☑' : '☐' }}
                        {{ $option }}
                    </div>
                @endforeach
            @elseif ($question->type === \App\Enums\QuestionType::TrueFalse)
                @foreach (['True', 'False'] as $option)
                    <div class="option {{ $withAnswers && strtolower($option) === strtolower(trim((string) $question->correct_answer)) ? 'correct' : '' }}">
                        {{ $withAnswers && strtolower($option) === strtolower(trim((string) $question->correct_answer)) ? '☑' : '☐' }}
                        {{ $option }}
                    </div>
                @endforeach
            @elseif (! $withAnswers)
                <div class="lines"><div></div><div></div></div>
            @endif

            @if ($withAnswers && empty($question->options) && $question->type !== \App\Enums\QuestionType::TrueFalse)
                <div class="answer">Expected answer: {{ $question->correct_answer }}</div>
            @endif
        </div>
    @endforeach

    <div class="footer">{{ config('app.name') }} · Generated {{ now()->format('d M Y, H:i') }}</div>
</body>
</html>
