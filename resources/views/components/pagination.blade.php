@props(['records'])

@if($records->hasPages())
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="small text-muted">
            Showing {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }} records
        </div>
        <div>{{ $records->links() }}</div>
    </div>
@endif
