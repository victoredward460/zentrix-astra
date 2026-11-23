@extends('layouts.app')

@section('title', $scope === 'security' ? 'Security events' : 'Platform activity')

@section('workspace')
    <div class="workspace-content grid">
        <header class="workspace-heading">
            <div>
                <p class="eyebrow">ADMINISTRATION / AUDIT TRAIL</p>
                <h1>{{ $scope === 'security' ? 'Security events' : 'Platform activity' }}</h1>
                <p class="workspace-intro">Minimized audit events. User content, credentials, submitted URLs, prompts, and file hashes are not included in audit metadata.</p>
            </div>
            <div class="workspace-actions">
                <a class="button button-outline button-small" href="{{ route('admin.activity.index') }}">All activity</a>
                <a class="button button-outline button-small" href="{{ route('admin.security-events.index') }}">Security events</a>
            </div>
        </header>
        <x-panel class="module-table-panel">
            @if ($events->isEmpty())
                <x-empty-state title="No events in this view" description="Events will appear as authentication and administrative operations occur." />
            @else
                <x-data-table class="w-full" :headers="['Time', 'Event', 'Actor', 'Subject', 'Safe metadata']" caption="Platform audit event history">
                    @foreach ($events as $event)
                        <tr>
                            <td><time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('M j, Y H:i:s') }}</time></td>
                            <td>{{ $event->event_key }}</td>
                            <td>{{ $event->actor?->email ?? 'Account no longer available' }}</td>
                            <td>{{ $event->subject_type ? $event->subject_type.' #'.$event->subject_id : '—' }}</td>
                            <td><code>{{ $event->metadata ? json_encode($event->metadata, JSON_UNESCAPED_SLASHES) : '—' }}</code></td>
                        </tr>
                    @endforeach
                </x-data-table>
                <div class="pagination-wrap">{{ $events->links() }}</div>
            @endif
        </x-panel>
    </div>
@endsection
