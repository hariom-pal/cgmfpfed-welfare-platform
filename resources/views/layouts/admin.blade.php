<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CGMFPFED Welfare Platform')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .app-shell { min-height: 100vh; display: grid; grid-template-columns: 280px 1fr; }
        .sidebar { background: #182433; color: #f8fafc; }
        .sidebar a { color: #cbd5e1; text-decoration: none; border-radius: .45rem; padding: .65rem .8rem; display: block; }
        .sidebar a.active, .sidebar a:hover { background: #2563eb; color: #fff; }
        .content { min-width: 0; }
        .page-header { background: #fff; border-bottom: 1px solid #e5e7eb; }
        .table-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; }
        @media (max-width: 992px) { .app-shell { grid-template-columns: 1fr; } .sidebar { position: static; } }
    </style>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar p-3">
        <div class="fw-bold fs-5 mb-4">CGMFPFED Welfare</div>
        <nav class="d-grid gap-1">
            <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Dashboard</a>
            <div class="small text-uppercase text-secondary mt-3 mb-1">Masters</div>
            @foreach(config('masters') as $key => $master)
                <a href="{{ route('masters.index', $key) }}" @class(['active' => request()->is('masters/'.$key.'*')])>{{ $master['label'] }}</a>
            @endforeach
            <div class="small text-uppercase text-secondary mt-3 mb-1">Workspace</div>
            <a href="{{ route('reports.index') }}" @class(['active' => request()->routeIs('reports.*')])>Reports</a>
            <a href="{{ route('settings.index') }}" @class(['active' => request()->routeIs('settings.*')])>Settings</a>
        </nav>
    </aside>
    <main class="content d-flex flex-column">
        <header class="page-header px-4 py-3 d-flex align-items-center justify-content-between">
            <div>
                <div class="text-muted small">Master Management</div>
                <h1 class="h4 mb-0">@yield('heading', 'Dashboard')</h1>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">admin</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-muted">Local Login</span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item" type="submit">Logout</button></form>
                    </li>
                </ul>
            </div>
        </header>
        <section class="p-4 flex-grow-1">
            <x-alert />
            @yield('content')
        </section>
        <footer class="px-4 py-3 small text-muted bg-white border-top">CGMFPFED Welfare Platform</footer>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>