@props(['title' => null, 'icon' => null])

<section {{ $attributes->merge(['class' => 'app-card']) }}>
    @if($title || $icon || isset($tools))
        <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="fw-semibold">
                @if($icon)<i class="{{ $icon }} me-2 text-primary"></i>@endif
                {{ $title }}
            </div>
            @isset($tools)
                <div>{{ $tools }}</div>
            @endisset
        </div>
    @endif
    <div class="p-3">
        {{ $slot }}
    </div>
</section>
