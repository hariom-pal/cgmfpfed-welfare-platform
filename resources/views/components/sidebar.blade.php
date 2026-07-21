@php
    $user = auth()->user();
    $menuPermissions = config('legacy_permissions.menu');
@endphp

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
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" @class(['nav-link', 'active' => request()->routeIs('dashboard')])>
                        <i class="nav-icon fa-solid fa-gauge-high"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                @if($user?->hasAnyPermission($menuPermissions['masters']))
                <li @class(['nav-item', 'menu-open' => request()->routeIs('masters.*')])>
                    <a href="#" @class(['nav-link', 'active' => request()->routeIs('masters.*')])>
                        <i class="nav-icon fa-solid fa-table-list"></i>
                        <p>Master Management <i class="nav-arrow fa-solid fa-angle-right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="#" class="nav-link disabled"><i class="nav-icon fa-regular fa-calendar"></i><p>Academic Session</p></a>
                        </li>
                        @foreach(config('masters') as $key => $master)
                            <li class="nav-item">
                                <a href="{{ route('masters.index', $key) }}" @class(['nav-link', 'active' => request()->is('masters/'.$key.'*')])>
                                    <i class="nav-icon fa-regular fa-circle"></i>
                                    <p>{{ $master['label'] }}</p>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
                @endif

                <li class="nav-header">OPERATIONS</li>
                @if($user?->hasAnyPermission($menuPermissions['applications']))
                <li class="nav-item">
                    <a href="{{ route('applications.index') }}" @class(['nav-link', 'active' => request()->routeIs('applications.*')])>
                        <i class="nav-icon fa-regular fa-file-lines"></i>
                        <p>Applications <span class="badge text-bg-secondary ms-1">Soon</span></p>
                    </a>
                </li>
                @endif
                @if($user?->hasAnyPermission($menuPermissions['workflow']))
                <li class="nav-item">
                    <a href="{{ route('workflow.index') }}" @class(['nav-link', 'active' => request()->routeIs('workflow.*')])>
                        <i class="nav-icon fa-solid fa-route"></i>
                        <p>Workflow <span class="badge text-bg-secondary ms-1">Soon</span></p>
                    </a>
                </li>
                @endif
                @if($user?->hasAnyPermission($menuPermissions['reports']))
                <li class="nav-item">
                    <a href="{{ route('reports.index') }}" @class(['nav-link', 'active' => request()->routeIs('reports.*')])>
                        <i class="nav-icon fa-solid fa-chart-column"></i>
                        <p>Reports <span class="badge text-bg-secondary ms-1">Soon</span></p>
                    </a>
                </li>
                @endif
                @if($user?->hasAnyPermission($menuPermissions['settings']))
                <li class="nav-item">
                    <a href="{{ route('settings.index') }}" @class(['nav-link', 'active' => request()->routeIs('settings.*')])>
                        <i class="nav-icon fa-solid fa-gear"></i>
                        <p>Settings <span class="badge text-bg-secondary ms-1">Soon</span></p>
                    </a>
                </li>
                @endif
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
