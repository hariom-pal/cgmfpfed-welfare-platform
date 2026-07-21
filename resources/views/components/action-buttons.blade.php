@props(['masterKey', 'record'])

<div class="btn-group btn-group-sm" role="group" aria-label="Record actions">
    <a class="btn btn-outline-secondary btn-icon" href="{{ route('masters.show', [$masterKey, $record->uuid]) }}" title="View">
        <i class="fa-regular fa-eye"></i>
    </a>
    <a class="btn btn-outline-primary btn-icon" href="{{ route('masters.edit', [$masterKey, $record->uuid]) }}" title="Edit">
        <i class="fa-regular fa-pen-to-square"></i>
    </a>
</div>
<form class="d-inline" method="POST" action="{{ route('masters.toggle', [$masterKey, $record->uuid]) }}">
    @csrf
    @method('PATCH')
    <button class="btn btn-sm btn-outline-warning btn-icon" type="submit" title="Toggle status">
        <i class="fa-solid fa-toggle-on"></i>
    </button>
</form>
<form id="delete-master-{{ $record->id }}" class="d-none" method="POST" action="{{ route('masters.destroy', [$masterKey, $record->uuid]) }}">
    @csrf
    @method('DELETE')
</form>
<button
    class="btn btn-sm btn-outline-danger btn-icon"
    type="button"
    title="Delete"
    data-delete-form="delete-master-{{ $record->id }}"
    data-record-name="{{ $record->name }}"
>
    <i class="fa-regular fa-trash-can"></i>
</button>
