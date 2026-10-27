@extends('admin.master_layout')
@section('title')
    <title>{{ __('Manage Subscribers') }} - {{ $mailingList->name }}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <x-admin.breadcrumb title="{{ __('Manage Subscribers') }} - {{ $mailingList->name }}" :list="[
                __('Dashboard') => route('admin.dashboard'),
                __('Mailing Lists') => route('admin.mailing-list-index'),
                __('Manage') => '#',
            ]" />

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ __('Subscribers in this list') }} ({{ $mailingList->subscribers->count() }})</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive table-invoice">
                                    <table class="table">
                                        <thead><tr><th>{{ __('SN') }}</th><th>{{ __('Email') }}</th><th>{{ __('Action') }}</th></tr></thead>
                                        <tbody>
                                            @forelse ($mailingList->subscribers as $index => $item)
                                                <tr>
                                                    <td>{{ $index + 1 }}</td>
                                                    <td>{{ html_decode($item->email) }}</td>
                                                    <td>
                                                        <form action="{{ route('admin.mailing-list-remove-subscriber', $mailingList->id) }}" method="POST" style="display:inline;">
                                                            @csrf
                                                            @method('DELETE')
                                                            <input type="hidden" name="subscriber_id" value="{{ $item->id }}">
                                                            <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('{{ __('Are you sure you want to remove this subscriber?') }}')"><i class="fas fa-minus-circle"></i> {{ __('Remove') }}</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="3" class="text-center">{{ __('No subscribers in this list.') }}</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header"><h4>{{ __('Add Subscribers to List') }}</h4></div>
                            <div class="card-body">
                                <form action="{{ route('admin.mailing-list-add-subscriber', $mailingList->id) }}" method="POST">
                                    @csrf
                                    <div class="form-group">
                                        <label>{{ __('Search and Add Subscriber by Email (verified)') }}</label>
                                        <select name="subscriber_ids[]" class="form-control select2" multiple required>
                                            @foreach ($availableSubscribers as $subscriber)
                                                <option value="{{ $subscriber->id }}">{{ $subscriber->email }}</option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">{{ __('Only verified subscribers are shown.') }}</small>
                                    </div>
                                    <button type="submit" class="btn btn-primary">{{ __('Add Selected Subscribers') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header"><h4>Importer des membres du site</h4></div>
                            <div class="card-body">
                                <form action="{{ route('admin.mailing-list-import-members', $mailingList->id) }}" method="POST">
                                    @csrf
                                    <div class="form-group">
                                        <label>Sélectionner les membres à ajouter à cette liste</label>
                                        <select name="member_ids[]" class="form-control select2" multiple required>
                                            @foreach ($members as $member)
                                                <option value="{{ $member->id }}">{{ optional($member->user)->name }} — {{ optional($member->user)->email }}</option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">Les doublons sont ignorés. Un membre désabonné ou non vérifié ne sera jamais réabonné automatiquement.</small>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Importer les membres sélectionnés</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
