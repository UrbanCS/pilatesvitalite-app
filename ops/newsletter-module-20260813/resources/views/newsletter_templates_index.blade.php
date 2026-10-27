@extends('admin.master_layout')
@section('title')
    <title>{!! __('Email Templates') !!}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <x-admin.breadcrumb title="{!! __('Email Templates') !!}" :list="[__('Dashboard') => route('admin.dashboard'), __('Templates') => '#']" />

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <a href="{{ route('admin.newsletter-templates.create') }}" class="btn btn-primary">{{ __('Create New Template') }}</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive table-invoice">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Template Name') }}</th>
                                                <th>{{ __('Default Subject') }}</th>
                                                <th>{{ __('Action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($templates as $template)
                                                <tr>
                                                    <td>{{ $template->name }}</td>
                                                    <td>{{ $template->subject }}</td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="{{ route('admin.newsletter-templates.edit', $template->id) }}" class="btn btn-info btn-sm"><i class="fas fa-edit"></i></a>
                                                            <a onclick="deleteData({{ $template->id }})" href="javascript:;" data-bs-toggle="modal" data-bs-target="#deleteModal" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <x-empty-table :name="__('')" route="" create="no" :message="__('No templates found!')" colspan="3"></x-empty-table>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    @push('js')
        <script>
            "use strict";
            function deleteData(id) {
                $("#deleteForm").attr("action", '{{ url('/admin/newsletter-templates') }}' + "/" + id + "/delete")
            }
        </script>
    @endpush
@endsection
