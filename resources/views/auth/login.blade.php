<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | CGMFPFED Welfare Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/admin-lte@4.1.0/dist/css/adminlte.min.css" rel="stylesheet">
    <link href="{{ asset('css/portal.css') }}" rel="stylesheet">
</head>
<body class="login-page">
<main class="container min-vh-100 d-flex flex-column align-items-center justify-content-center py-4">
    <form method="POST" action="{{ route('login.store') }}" class="login-card bg-white border p-4 shadow-lg">
        @csrf
        <div class="text-center mb-4">
            <div class="login-emblem mx-auto mb-3"><i class="fa-solid fa-landmark"></i></div>
            <div class="small text-uppercase text-primary fw-semibold">Government Enterprise Portal</div>
            <h1 class="h4 mb-1">CGMFPFED Welfare Platform</h1>
            <p class="text-muted mb-0">Legacy User Login</p>
        </div>
        <x-alert />
        <div class="mb-3">
            <label for="username" class="form-label required">Email or Mobile</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                <input id="username" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username') }}" autofocus required>
                @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label required">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" required>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="form-check">
                <input id="remember" name="remember" value="1" class="form-check-input" type="checkbox">
                <label class="form-check-label" for="remember">Remember Me</label>
            </div>
            <button class="btn btn-link p-0 text-muted" type="button" disabled>Forgot Password</button>
        </div>
        <button type="submit" class="btn btn-primary w-100">
            <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
        </button>
    </form>
    <footer class="text-white-50 small mt-4 text-center">
        Chhattisgarh Minor Forest Produce Federation Welfare Platform
    </footer>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
