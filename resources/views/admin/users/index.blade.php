@extends('layouts.app')

@section('title', 'User management')

@section('workspace')
    <div class="workspace-content grid">
        <div class="workspace-heading">
            <div>
                <p class="eyebrow">ADMINISTRATION / ACCESS CONTROL</p>
                <h1>User management</h1>
                <p class="workspace-intro">Review account access, assign roles, and disable accounts. Public registration cannot grant administrator access.</p>
            </div>
        </div>

        @if (session('status'))
            <p class="form-notice" role="status">{{ session('status') }}</p>
        @endif

        <x-panel class="module-table-panel">
            @if ($errors->has('role'))
                <p class="form-error" role="alert">{{ $errors->first('role') }}</p>
            @endif
            @if ($errors->has('is_active'))
                <p class="form-error" role="alert">{{ $errors->first('is_active') }}</p>
            @endif
            <x-data-table class="w-full" :headers="['User', 'Email', 'Role', 'Account status', 'Administration']" caption="Registered ZENTRIX ASTRA users">
                @foreach ($users as $user)
                    <tr>
                        <th scope="row">{{ $user->name }}</th>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->role->value === 'admin' ? 'Administrator' : 'User' }}</td>
                        <td><x-status-badge :tone="$user->is_active ? 'good' : 'muted'">{{ $user->is_active ? 'Active' : 'Disabled' }}</x-status-badge></td>
                        <td>
                            @if (auth()->user()->is($user))
                                <span class="form-hint">Your own role and status cannot be changed here.</span>
                            @else
                                <form class="role-form" method="POST" action="{{ route('admin.users.role.update', $user) }}">
                                    @csrf
                                    @method('PUT')
                                    <label class="sr-only" for="role-{{ $user->id }}">Role for {{ $user->name }}</label>
                                    <select class="auth-input role-select" id="role-{{ $user->id }}" name="role">
                                        <option value="user" @selected($user->role->value === 'user')>User</option>
                                        <option value="admin" @selected($user->role->value === 'admin')>Administrator</option>
                                    </select>
                                    <button class="button button-outline button-small" type="submit">Save role</button>
                                </form>
                                <form class="role-form" method="POST" action="{{ route('admin.users.status.update', $user) }}">
                                    @csrf
                                    @method('PUT')
                                    <label class="sr-only" for="active-{{ $user->id }}">Account status for {{ $user->name }}</label>
                                    <select class="auth-input role-select" id="active-{{ $user->id }}" name="is_active">
                                        <option value="1" @selected($user->is_active)>Active</option>
                                        <option value="0" @selected(! $user->is_active)>Disabled</option>
                                    </select>
                                    <button class="button button-outline button-small" type="submit">Save status</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
            <div class="pagination-wrap">{{ $users->links() }}</div>
        </x-panel>
    </div>
@endsection
