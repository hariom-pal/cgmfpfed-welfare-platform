<aside class="app-sidebar shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="brand-link text-decoration-none">
            <span class="brand-image"><i class="fa-solid fa-landmark"></i></span>
            <span class="brand-text fw-semibold ms-2">CGMFPFED Welfare</span>
        </a>
    </div>

    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column nav-sidebar" data-lte-toggle="treeview" role="menu">
                @foreach($menuItems ?? [] as $item)
                    @php
                        $children = $item['children'] ?? [];
                        $activePatterns = $item['active'] ?? [];
                        $isActive = collect($activePatterns)->contains(fn (string $pattern): bool => request()->routeIs($pattern));
                        $childActive = collect($children)->contains(function (array $child): bool {
                            return collect($child['active'] ?? [])->contains(fn (string $pattern): bool => request()->routeIs($pattern));
                        });
                    @endphp
                    <li @class(['nav-item', 'menu-open' => $children !== [] && ($isActive || $childActive)])>
                        <a href="{{ $item['url'] ?? '#' }}" @class(['nav-link', 'active' => $isActive || $childActive, 'disabled' => $item['disabled'] ?? false]) @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                            <i class="nav-icon {{ $item['icon'] }}"></i>
                            <p>
                                {{ $item['label'] }}
                                @if($children !== [])
                                    <i class="nav-arrow fa-solid fa-angle-right"></i>
                                @endif
                            </p>
                        </a>
                        @if($children !== [])
                            <ul class="nav nav-treeview">
                                @foreach($children as $child)
                                    @php
                                        $childActive = collect($child['active'] ?? [])->contains(fn (string $pattern): bool => request()->routeIs($pattern));
                                    @endphp
                                    <li class="nav-item">
                                        <a href="{{ $child['url'] ?? '#' }}" @class(['nav-link', 'active' => $childActive, 'disabled' => $child['disabled'] ?? false])>
                                            <i class="nav-icon {{ $child['icon'] }}"></i>
                                            <p>{{ $child['label'] }}</p>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
                <li class="nav-item mt-2">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="nav-link border-0 w-100 text-start" type="submit">
                            <i class="nav-icon fa-solid fa-right-from-bracket"></i>
                            <p>Logout</p>
                        </button>
                    </form>
                </li>
            </ul>
        </nav>
    </div>
</aside>
