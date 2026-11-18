@extends('admin.layout')
@section('title', 'Home')
@section('page-class', 'mmc-start-page')
@section('hide-page-heading', '1')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/admin-start.css') }}?v={{ filemtime(public_path('admin-assets/admin-start.css')) }}">
<div class="admin-start">
    <header class="admin-start-heading">
        <div>
            <span class="admin-start-eyebrow"><i class="fas fa-th-large" aria-hidden="true"></i> Super Admin workspace</span>
            <h1>Where would you like to start?</h1>
            <p>Choose a module to open.</p>
        </div>
        <div class="admin-start-shortcuts" aria-label="Workspace shortcuts">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-primary"><i class="fas fa-chart-bar" aria-hidden="true"></i> Dashboard</a>
            <a href="{{ route('admin.notes.index') }}" class="btn btn-outline-primary"><i class="fas fa-sticky-note" aria-hidden="true"></i> My Notes</a>
        </div>
    </header>

    <div class="admin-start-sections">
    @foreach(config('admin_start') as $section => $modules)
    <section class="admin-start-section" aria-labelledby="module-section-{{ $loop->index }}">
        <h2 id="module-section-{{ $loop->index }}">{{ $section }}</h2>
        <div class="admin-module-grid">
            @foreach($modules as $module)
            <a class="admin-module-card" href="{{ route($module['route']) }}" aria-labelledby="module-label-{{ $loop->parent->index }}-{{ $loop->index }}">
                <span class="admin-module-top"><span class="admin-module-icon"><i class="fas fa-{{ $module['icon'] }}" aria-hidden="true"></i></span><i class="fas fa-arrow-right admin-module-arrow" aria-hidden="true"></i></span>
                <span class="admin-module-label" id="module-label-{{ $loop->parent->index }}-{{ $loop->index }}">{{ $module['label'] }}</span>
                <span class="admin-module-description">{{ $module['description'] }}</span>
            </a>
            @endforeach
        </div>
    </section>
    @endforeach
    </div>
</div>
@endsection
