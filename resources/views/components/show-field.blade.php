@props(['label', 'value' => null])

<div {{ $attributes->class(['col-md-6']) }}>
    <div class="small text-muted">{{ $label }}</div>
    <div class="fw-semibold text-break">{{ filled($value) ? $value : 'N/A' }}</div>
</div>
