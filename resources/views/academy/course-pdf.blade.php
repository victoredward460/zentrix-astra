<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $course->title }}</title>
    <style>
        @page { margin: 48px 52px; }
        body { color: #172235; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.65; }
        h1 { color: #0c2b43; font-size: 25px; line-height: 1.2; margin: 0 0 8px; }
        h2 { border-bottom: 1px solid #d5e0e9; color: #0c2b43; font-size: 16px; margin: 28px 0 8px; padding-bottom: 5px; }
        h3 { color: #1d5b78; font-size: 13px; margin: 17px 0 4px; }
        p { margin: 5px 0 10px; }
        .eyebrow { color: #547086; font-size: 9px; font-weight: bold; letter-spacing: 1.6px; text-transform: uppercase; }
        .summary { color: #40576b; font-size: 13px; margin-bottom: 12px; }
        .meta { color: #597083; font-size: 10px; margin-bottom: 24px; }
        .lesson { page-break-inside: avoid; }
        .quiz { background: #f2f7fa; border-left: 3px solid #43aabc; margin: 12px 0; padding: 8px 12px; }
        .question { font-weight: bold; margin-top: 8px; }
        .option { margin-left: 12px; }
        .answer { color: #245c4b; font-size: 10px; }
        footer { border-top: 1px solid #d5e0e9; color: #607789; font-size: 9px; margin-top: 32px; padding-top: 8px; }
    </style>
</head>
<body>
    <p class="eyebrow">{{ $course->category->name }} · {{ strtoupper($course->level) }}</p>
    <h1>{{ $course->title }}</h1>
    <p class="summary">{{ $course->summary }}</p>
    <p class="meta">{{ $course->estimated_minutes }} minutes · {{ $course->lessons->count() }} lessons</p>

    @foreach ($course->lessons as $index => $lesson)
        <section class="lesson">
            <h2>{{ $index + 1 }}. {{ $lesson->title }}</h2>
            @if ($lesson->summary)
                <p><strong>{{ $lesson->summary }}</strong></p>
            @endif
            <p>{!! nl2br(e($lesson->content)) !!}</p>

            @if ($lesson->quiz?->is_published && $lesson->quiz->questions->isNotEmpty())
                <div class="quiz">
                    <h3>{{ $lesson->quiz->title }}</h3>
                    @foreach ($lesson->quiz->questions as $question)
                        <p class="question">{{ $loop->iteration }}. {{ $question->prompt }}</p>
                        @foreach ($question->options as $key => $option)
                            <p class="option">{{ strtoupper($key) }}. {{ $option }}</p>
                        @endforeach
                        <p class="answer">Answer: {{ strtoupper($question->correct_option) }}. {{ $question->options[$question->correct_option] ?? '' }} {{ $question->explanation }}</p>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach

    <footer>Generated for offline learning. Review answers after completing each knowledge check.</footer>
</body>
</html>
