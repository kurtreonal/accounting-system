<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | NEXII Accounting</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="demo-dashboard">
    <main class="dashboard-card">
        <span class="brand-mark">NX</span>
        <p class="eyebrow">Static JSON demo</p>
        <h1>Welcome, {{ $user['name'] }}</h1>
        <p>You signed in as <strong>{{ $user['role'] }}</strong>. The full dashboard will be built from the approved project pages.</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="sign-in-button" type="submit">Sign Out</button>
        </form>
    </main>
</body>
</html>
