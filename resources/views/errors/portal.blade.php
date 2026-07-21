<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') | @yield('title')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/admin-lte@4.1.0/dist/css/adminlte.min.css" rel="stylesheet">
    <link href="{{ asset('css/portal.css') }}" rel="stylesheet">
</head>
<body class="login-page">
<main class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
    <section class="login-card bg-white border p-4 shadow-lg text-center">
        <div class="login-emblem mx-auto mb-3"><i class="fa-solid fa-landmark"></i></div>
        <div class="display-5 fw-bold text-primary">@yield('code')</div>
        <h1 class="h4">@yield('title')</h1>
        <p class="text-muted">@yield('message')</p>
        <a class="btn btn-primary" href="{{ route('login') }}">
            <i class="fa-solid fa-arrow-left me-1"></i>Return to Login
        </a>
    </section>
</main>
</body>
</html>
