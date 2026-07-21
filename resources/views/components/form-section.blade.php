@props(['title', 'icon' => 'fa-regular fa-rectangle-list'])

<div class="mb-3 pb-2 border-bottom">
    <h2 class="h5 mb-1"><i class="{{ $icon }} me-2 text-primary"></i>{{ $title }}</h2>
    <div class="text-muted small">Fields marked with <span class="text-danger">*</span> are required.</div>
</div>
