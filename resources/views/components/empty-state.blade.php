@props(['title' => 'No Records Found', 'message' => 'Create a record to begin using this master.', 'action' => null, 'actionLabel' => 'Create New'])

<div class="empty-state">
    <div class="empty-state-icon mb-3"><i class="fa-regular fa-folder-open"></i></div>
    <h2 class="h5">{{ $title }}</h2>
    <p class="text-muted mb-3">{{ $message }}</p>
    @if($action)
        <a href="{{ $action }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>{{ $actionLabel }}
        </a>
    @endif
</div>
