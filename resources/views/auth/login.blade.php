<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Local Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container min-vh-100 d-flex align-items-center justify-content-center">
    <form method="POST" action="{{ route('login.store') }}" class="bg-white border rounded p-4 shadow-sm" style="width: min(100%, 420px);">
        @csrf
        <h1 class="h4 mb-1">CGMFPFED Welfare</h1>
        <p class="text-muted mb-4">Temporary local admin access</p>
        <x-alert />
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input id="username" name="username" class="form-control" value="{{ old('username') }}" autofocus>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input id="password" name="password" type="password" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
</main>
</body>
</html>