@extends('layouts.app')

@section('title', 'Scan activity')

@section('workspace')
    <div class="workspace-content grid">
        <header class="workspace-heading">
            <div><p class="eyebrow">ADMINISTRATION / ASSESSMENTS</p><h1>Scan activity</h1><p class="workspace-intro">Administrative report index with each scan linked to its owner. User-facing access remains owner-scoped.</p></div>
        </header>
        <x-panel class="module-table-panel">
            @if ($scans->isEmpty())
                <x-empty-state title="No scans recorded" description="Scan runs will appear after a user completes an authorized assessment." />
            @else
                <x-data-table class="w-full" :headers="['Requested', 'Owner', 'Target', 'Type', 'Status', 'Findings', 'Report']" caption="Scan activity linked to user accounts">
                    @foreach ($scans as $scan)
                        <tr>
                            <td><time datetime="{{ $scan->requested_at->toIso8601String() }}">{{ $scan->requested_at->format('M j, Y H:i') }}</time></td>
                            <td>{{ $scan->user?->email ?? 'Deleted account' }}</td>
                            <td>{{ $scan->securityAsset?->hostname ?? 'Unavailable' }}</td>
                            <td>{{ str($scan->scan_type->value)->replace('_', ' ')->title() }}</td>
                            <td>{{ str($scan->status->value)->title() }}</td>
                            <td>{{ number_format($scan->findings_count) }}</td>
                            <td><a class="text-link" href="{{ route('admin.scans.show', $scan) }}">View report</a></td>
                        </tr>
                    @endforeach
                </x-data-table>
                <div class="pagination-wrap">{{ $scans->links() }}</div>
            @endif
        </x-panel>
    </div>
@endsection
