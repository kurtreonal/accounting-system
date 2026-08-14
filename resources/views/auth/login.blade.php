<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in to the NEXII Enterprise Accounting System.">
    <meta name="theme-color" content="#17336f">
    <title>Sign in | NEXII Accounting</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen min-w-80 bg-white font-sans text-slate-900 antialiased">
    <main class="grid min-h-screen lg:grid-cols-[420px_minmax(0,1fr)] xl:grid-cols-[30.75%_minmax(0,1fr)]">
        <section class="relative min-h-72 overflow-hidden bg-gradient-to-br from-[#19336f] via-[#1d4397] to-[#2459e1] text-white lg:min-h-screen" aria-labelledby="product-heading">
            <div class="absolute -top-28 -left-28 size-72 rounded-full bg-white/[0.035]" aria-hidden="true"></div>
            <div class="absolute -right-32 -bottom-32 size-96 rounded-full bg-blue-300/[0.06]" aria-hidden="true"></div>

            <div class="relative flex min-h-72 flex-col px-7 py-7 sm:px-10 sm:py-10 lg:min-h-screen">
                <a href="{{ route('login') }}" class="block size-24 shrink-0 overflow-hidden rounded-xl bg-white shadow-xl shadow-blue-950/20" aria-label="Nexii Tech Solutions login">
                    <img
                        src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(resource_path('assets/nexii_logo.jpg'))) }}"
                        alt="Nexii Tech Solutions Inc."
                        class="block size-full object-cover"
                    >
                </a>

                <div class="mt-14 max-w-sm lg:absolute lg:top-1/2 lg:left-10 lg:mt-0 lg:-translate-y-[22%]">
                    <p class="text-[10px] font-semibold tracking-[0.24em] text-blue-200 uppercase">Accounting System</p>
                    <h1 id="product-heading" class="mt-5 text-[32px] leading-[1.16] font-bold tracking-[-0.035em] sm:text-[34px]">Nexii Tech<br>Solutions Inc.</h1>
                    <p class="mt-5 max-w-xs text-sm leading-5 text-blue-200/75">Custom business systems, ERP solutions,<br class="hidden lg:block"> AI automation, and digital transformation.</p>
                </div>
            </div>
        </section>

        <section class="grid min-h-[calc(100vh-18rem)] place-items-center bg-white px-5 py-12 sm:px-8 lg:min-h-screen lg:px-12" aria-labelledby="sign-in-heading">
            <div class="w-full max-w-[340px]">
                <header>
                    <h2 id="sign-in-heading" class="text-[24px] leading-tight font-bold tracking-[-0.035em] text-slate-900">Welcome back</h2>
                    <p class="mt-1.5 text-sm text-slate-400">Sign in to your account</p>
                </header>

                <form method="POST" action="{{ route('login.attempt') }}" class="mt-9" novalidate>
                    @csrf

                    @if ($errors->any())
                        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-3.5 py-3 text-xs leading-5 text-red-700" role="alert">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <div>
                        <label for="email" class="mb-2 block text-xs font-semibold text-slate-500 uppercase">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            autocomplete="username"
                            required
                            autofocus
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                            class="h-[43px] w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 text-sm text-slate-800 outline-none transition placeholder:text-slate-300 hover:border-slate-300 focus:border-blue-500 focus:bg-white focus:ring-3 focus:ring-blue-100"
                            placeholder="you@example.com"
                        >
                    </div>

                    <div class="mt-10">
                        <label for="password" class="mb-2 block text-xs font-semibold text-slate-500 uppercase">Password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            class="h-[43px] w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 text-sm text-slate-800 outline-none transition placeholder:text-slate-300 hover:border-slate-300 focus:border-blue-500 focus:bg-white focus:ring-3 focus:ring-blue-100"
                            placeholder="Enter your password"
                        >
                    </div>

                    <button type="submit" class="mt-8 flex h-[42px] w-full cursor-pointer items-center justify-center rounded-lg bg-gradient-to-r from-[#2447ac] to-[#2459df] text-sm font-semibold text-white shadow-lg shadow-blue-700/20 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-blue-700/25 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-blue-500 active:translate-y-0 disabled:cursor-wait disabled:opacity-70">Sign In</button>
                </form>

                <p class="mt-8 text-center text-[11px] text-slate-300">Demo environment · Static demonstration access</p>
            </div>
        </section>
    </main>
</body>
</html>
