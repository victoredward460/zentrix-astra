@extends('layouts.app')

@section('title', 'Academy progress')

@section('workspace')
    <div class="workspace-content academy-content">
        <header class="workspace-heading">
            <div><p class="eyebrow">ADMINISTRATION / ACADEMY</p><h1>Learner progress</h1><p class="workspace-intro">Progress and quiz outcomes are linked to account owners. Quiz answers and submitted answer details are not shown here.</p></div>
            <a class="button button-outline button-small" href="{{ route('admin.academy.index') }}">Manage content</a>
        </header>

        <section class="academy-admin-section" aria-labelledby="lesson-progress-heading">
            <div class="dashboard-section-heading"><div><p class="eyebrow">LESSONS</p><h2 id="lesson-progress-heading">Lesson progress</h2></div></div>
            <x-panel class="module-table-panel">
                @if ($progressRecords->isEmpty())
                    <x-empty-state title="No lesson progress recorded" description="Learner progress will appear here as users view and complete lessons." />
                @else
                    <x-data-table class="w-full" :headers="['Learner', 'Course', 'Lesson', 'Last viewed', 'Completion']" caption="Learner lesson progress">
                        @foreach ($progressRecords as $progress)
                            <tr>
                                <td>{{ $progress->user?->email ?? 'Deleted account' }}</td>
                                <td>{{ $progress->lesson?->course?->title ?? 'Unavailable' }}</td>
                                <td>{{ $progress->lesson?->title ?? 'Unavailable' }}</td>
                                <td>{{ $progress->last_viewed_at?->format('M j, Y H:i') ?? 'Not viewed' }}</td>
                                <td>{{ $progress->completed_at?->format('M j, Y H:i') ?? 'In progress' }}</td>
                            </tr>
                        @endforeach
                    </x-data-table>
                    <div class="pagination-wrap">{{ $progressRecords->links() }}</div>
                @endif
            </x-panel>
        </section>

        <section class="academy-admin-section" aria-labelledby="quiz-attempts-heading">
            <div class="dashboard-section-heading"><div><p class="eyebrow">ASSESSMENTS</p><h2 id="quiz-attempts-heading">Quiz attempt history</h2></div></div>
            <x-panel class="module-table-panel">
                @if ($quizAttempts->isEmpty())
                    <x-empty-state title="No quiz attempts recorded" description="Submitted attempts and pass/fail results will be listed here." />
                @else
                    <x-data-table class="w-full" :headers="['Learner', 'Course / lesson', 'Score', 'Result', 'Submitted']" caption="Academy quiz attempt history">
                        @foreach ($quizAttempts as $attempt)
                            <tr>
                                <td>{{ $attempt->user?->email ?? 'Deleted account' }}</td>
                                <td>{{ $attempt->quiz?->lesson?->course?->title ?? 'Unavailable' }} / {{ $attempt->quiz?->lesson?->title ?? 'Unavailable' }}</td>
                                <td>{{ $attempt->score }} / {{ $attempt->question_count }}</td>
                                <td><x-status-badge :tone="$attempt->passed ? 'good' : 'muted'">{{ $attempt->passed ? 'Passed' : 'Not passed' }}</x-status-badge></td>
                                <td>{{ $attempt->submitted_at->format('M j, Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </x-data-table>
                    <div class="pagination-wrap">{{ $quizAttempts->links() }}</div>
                @endif
            </x-panel>
        </section>
    </div>
@endsection
