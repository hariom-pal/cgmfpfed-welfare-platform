<nav class="app-header navbar navbar-expand bg-white border-bottom">
    <div class="container-fluid">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="fa-solid fa-bars"></i>
                </a>
            </li>
            <li class="nav-item d-none d-md-block">
                <span class="nav-link fw-semibold">
                    <i class="fa-solid fa-landmark me-2 text-primary"></i>CGMFPFED Welfare Platform
                </span>
            </li>
        </ul>

        <form class="d-none d-lg-flex ms-auto me-3" role="search">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input class="form-control" type="search" placeholder="Global search" disabled>
            </div>
        </form>

        <ul class="navbar-nav align-items-center">
            <li class="nav-item">
                <button class="btn btn-link nav-link position-relative" type="button" disabled>
                    <i class="fa-regular fa-bell"></i>
                    <span class="position-absolute top-25 start-75 translate-middle badge rounded-pill text-bg-warning">0</span>
                </button>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" href="#">
                    <span class="badge rounded-pill text-bg-primary">A</span>
                    <span class="d-none d-sm-inline">admin</span>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <span class="dropdown-item-text">
                        <span class="fw-semibold d-block">Administrator</span>
                        <span class="small text-muted">Local Admin</span>
                    </span>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="dropdown-item" type="submit">
                            <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
                        </button>
                    </form>
                </div>
            </li>
        </ul>
    </div>
</nav>
