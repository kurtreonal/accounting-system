<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in to the NEXII Enterprise Accounting System demo.">
    <title>Sign in | NEXII Accounting</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-hero" aria-labelledby="product-heading">
            <div class="hero-orb hero-orb-top" aria-hidden="true"></div>
            <div class="hero-orb hero-orb-bottom" aria-hidden="true"></div>

            <div class="hero-content">
                <a class="brand" href="{{ route('login') }}" aria-label="NEXII Tech Solutions home">
                    <span class="brand-mark">NX</span>
                    <span>NEXII Tech Solutions Inc.</span>
                </a>

                <div class="hero-copy">
                    <h1 id="product-heading">Enterprise Accounting,<br>Simplified.</h1>
                    <p>
                        Complete double-entry accounting system built for Philippine businesses.
                        Manage your chart of accounts, journal entries, and financial reports — all in one place.
                    </p>

                    <div class="system-stats" aria-label="System highlights">
                        <div class="stat-card"><strong>38+</strong><span>Accounts</span></div>
                        <div class="stat-card"><strong>12</strong><span>Modules</span></div>
                        <div class="stat-card"><strong>9</strong><span>Reports</span></div>
                        <div class="stat-card"><strong>4</strong><span>Roles</span></div>
                    </div>

                    <p class="hero-note">Enterprise Accounting System · BIR-Ready · Demo Mode</p>
                </div>
            </div>
        </section>

        <section class="login-panel" aria-labelledby="sign-in-heading">
            <div class="login-card">
                <header>
                    <h2 id="sign-in-heading">Sign in</h2>
                    <p>Access your accounting dashboard</p>
                </header>

                <form method="POST" action="{{ route('login.attempt') }}" autocomplete="off" novalidate>
                    @csrf

                    @if ($errors->any())
                        <div class="form-alert" role="alert">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <div class="form-field">
                        <label for="email">Email Address</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            autocomplete="off"
                            required
                            autofocus
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                        >
                    </div>

                    <div class="form-field">
                        <label for="password">Password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="role">Sign in as</label>
                        <select id="role" name="role" autocomplete="off" required>
                            <option value="" disabled @selected(old('role') === null)>Select a role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected(old('role') === $role)>
                                    {{ $role }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button class="sign-in-button" type="submit">Sign In</button>
                </form>

                <p class="demo-notice">Demo data only · No real transactions processed</p>
            </div>
        </section>
    </main>
</body>
</html>
