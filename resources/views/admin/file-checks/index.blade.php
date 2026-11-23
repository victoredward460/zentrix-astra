@extends('layouts.app')

@section('title', 'File Check activity')

@section('workspace')
    <div class="workspace-content grid">
        <header class="workspace-heading">
            <div><p class="eyebrow">ADMINISTRATION / FILE CHECK</p><h1>File Check records</h1><p class="workspace-intro">Owner-linked metadata and hash reports. Uploaded files are not executed or exposed through the administration interface.</p></div>
        </header>
        <x-panel class="module-table-panel">
            @if ($fileChecks->isEmpty())
                <x-empty-state title="No File Check records" description="Records will appear when users submit files for metadata and hashing." />
            @else
                <x-data-table class="w-full" :headers="['Submitted', 'Owner', 'File name', 'Size', 'Status', 'Report']" caption="File Check metadata records linked to their owners">
                    @foreach ($fileChecks as $fileCheck)
                        <tr>
                            <td><time datetime="{{ $fileCheck->created_at->toIso8601String() }}">{{ $fileCheck->created_at->format('M j, Y H:i') }}</time></td>
                            <td>{{ $fileCheck->user?->email ?? 'Deleted account' }}</td>
                            <td>{{ $fileCheck->original_name }}</td>
                            <td>{{ number_format($fileCheck->size_bytes) }} bytes</td>
                            <td>{{ str($fileCheck->status->value)->title() }}</td>
                            <td><a class="text-link" href="{{ route('admin.file-checks.show', $fileCheck) }}">View report</a></td>
                        </tr>
                    @endforeach
                </x-data-table>
                <div class="pagination-wrap">{{ $fileChecks->links() }}</div>
            @endif
        </x-panel>
    </div>
@endsection
