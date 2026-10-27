@extends('admin.master_layout')
@section('title')
    <title>{!! __('Edit Email Template') !!}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <x-admin.breadcrumb title="{!! __('Edit Email Template') !!}" :list="[__('Dashboard') => route('admin.dashboard'), __('Templates') => route('admin.newsletter-templates.index'), __('Edit') => '#']" />
            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form action="{{ route('admin.newsletter-templates.update', $template->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="form-group">
                                        <label for="name">{{ __('Template Name (Internal)') }}<span class="text-danger">*</span></label>
                                        <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $template->name) }}">
                                    </div>
                                    <div class="form-group">
                                        <label for="subject">{{ __('Default Subject') }}<span class="text-danger">*</span></label>
                                        <input type="text" name="subject" id="subject" class="form-control" value="{{ old('subject', $template->subject) }}">
                                    </div>
                                    <div class="form-group">
                                        <label for="email_content_editor">{{ __('Content') }}<span class="text-danger">*</span></label>
                                        <textarea name="content" class="summernote" id="email_content_editor" cols="30" rows="10">{{ old('content', $template->content) }}</textarea>
                                    </div>
                                    <button class="btn btn-primary">{{ __('Update Template') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
