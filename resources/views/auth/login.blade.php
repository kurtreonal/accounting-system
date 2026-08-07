<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in to the NEXII Enterprise Accounting System.">
    <title>Sign in | NEXII Accounting</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen min-w-80 bg-slate-50 font-sans text-[#101828]">
    <main class="grid min-h-screen grid-cols-[minmax(460px,38.6%)_1fr] max-[900px]:block">
        <section class="relative min-h-screen overflow-hidden bg-[#204be7] text-white max-[900px]:min-h-0" aria-labelledby="product-heading">
            <div class="absolute -top-47.75left-[191px] size-95.5 rounded-full bg-white/8" aria-hidden="true"></div>
            <div class="absolute top-[36.3%] -right-24 size-72 rounded-full bg-white/8 max-[520px]:top-[52%] max-[520px]:-right-40" aria-hidden="true"></div>

            <div class="relative z-10 min-h-full w-full max-w-143.5 px-12 pt-12 pb-13 max-[900px]:max-w-none max-[900px]:px-7 max-[900px]:pt-7 max-[900px]:pb-9 max-[520px]:px-5 max-[520px]:pt-6 max-[520px]:pb-7.5">
                <a class="inline-flex items-center gap-3.25 text-lg font-semibold text-inherit no-underline max-[520px]:gap-2.5 max-[520px]:text-base" href="{{ route('login') }}" aria-label="NEXII Tech Solutions home">
                    <span class="inline-grid size-10 shrink-0 place-items-center rounded-xl bg-white/18 text-[17px] font-bold tracking-[0.02em] text-white max-[520px]:size-9.5 max-[520px]:rounded-[11px] max-[520px]:text-base">NX</span>
                    <span>NEXII Tech Solutions Inc.</span>
                </a>

                <div class="mt-16.75 w-full max-[900px]:mt-10 max-[520px]:mt-8.5">
                    <h1 class="m-0 text-[clamp(30px,2.1vw,36px)] leading-[1.18] font-[650] tracking-[-0.035em]" id="product-heading">Enterprise Accounting,<br>Simplified.</h1>
                    <p class="mt-5 mb-0 max-w-97.5 text-[15px] leading-[1.55] text-[#c8d6ff] max-[900px]:max-w-142.5">
                        Complete double-entry accounting system built for Philippine businesses.
                        Manage your chart of accounts, journal entries, and financial reports &mdash; all in one place.
                    </p>

                    <div class="mt-0.5 grid max-w-142.5 grid-cols-2 gap-4 max-[520px]:mt-5 max-[520px]:gap-2.5" aria-label="System highlights">
                        <div class="flex min-h-20.5 flex-col justify-center rounded-xl bg-white/9 px-4 py-3.5 max-[520px]:min-h-18.5"><strong class="text-[25px] leading-none">38+</strong><span class="mt-1.75 text-[13px] text-[#bdceff]">Accounts</span></div>
                        <div class="flex min-h-20.5 flex-col justify-center rounded-xl bg-white/9 px-4 py-3.5 max-[520px]:min-h-18.5"><strong class="text-[25px] leading-none">12</strong><span class="mt-1.75 text-[13px] text-[#bdceff]">Modules</span></div>
                        <div class="flex min-h-20.5 flex-col justify-center rounded-xl bg-white/9 px-4 py-3.5 max-[520px]:min-h-18.5"><strong class="text-[25px] leading-none">9</strong><span class="mt-1.75 text-[13px] text-[#bdceff]">Reports</span></div>
                        <div class="flex min-h-20.5 flex-col justify-center rounded-xl bg-white/9 px-4 py-3.5 max-[520px]:min-h-18.5"><strong class="text-[25px] leading-none">4</strong><span class="mt-1.75 text-[13px] text-[#bdceff]">Roles</span></div>
                    </div>

                    <p class="mt-6.5 mb-0 text-xs text-[#9db6ff] max-[520px]:leading-6">Enterprise Accounting System</p>
                </div>
            </div>
        </section>

        <section class="grid min-h-screen place-items-center bg-slate-50 p-12 max-[900px]:min-h-0 max-[900px]:px-7 max-[900px]:pt-13 max-[900px]:pb-16 max-[520px]:px-5 max-[520px]:pt-10.5 max-[520px]:pb-14" aria-labelledby="sign-in-heading">
            <div class="w-full max-w-96 -translate-y-1 max-[900px]:translate-y-0">
                <header>
                    <h2 class="m-0 text-[26px] leading-[1.2] font-[650] tracking-tight text-[#101828]" id="sign-in-heading">Sign in</h2>
                    <p class="mt-1.5 mb-8 text-[15px] text-[#6b7c9b] max-[520px]:mb-7">Access your accounting dashboard</p>
                </header>

                <form method="POST" action="{{ route('login.attempt') }}" autocomplete="off" novalidate>
                    @csrf

                    @if ($errors->any())
                        <div class="-mt-3.5 mb-4.5 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-[13px] text-[#b42318]" role="alert">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <div class="mb-3.75">
                        <label class="mb-1 block text-xs font-medium text-[#344563]" for="email">Email Address</label>
                        <input
                            class="p-4 h-10.75 w-full rounded-lg border border-[#d8e0eb] bg-white px-3.25ext-sm text-[#1d2939] outline-none transition-[border-color,box-shadow] duration-150 focus:border-blue-600 focus:ring-3 focus:ring-blue-600/10 max-[520px]:min-h-11.5"
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

                    <div class="mb-3.75
                        <label class="mb-1 block text-xs font-medium text-[#344563]" for="password">Password</label>
                        <input
                            class="h-10.75 w-full rounded-lg border border-[#d8e0eb] bg-white px-3.25 text-sm text-[#1d2939] outline-none transition-[border-color,box-shadow] duration-150 focus:border-blue-600 focus:ring-3 focus:ring-blue-600/10 max-[520px]:min-h-11.5
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="mb-3.75">
                        <label class="mb-1 block text-xs font-medium text-[#344563]" for="role">Sign in as</label>
                        <select class="p-2 h-10.75w-full cursor-pointer rounded-lg border border-[#d8e0eb] bg-white ppx-3.25text-sm text-[#1d2939] outline-none transition-[border-color,box-shadow] duration-150 focus:border-blue-600 focus:ring-3 focus:ring-blue-600/10 max-[520px]:min-h-11.5" id="role" name="role" autocomplete="off" required>
                            <option value="" disabled @selected(old('role') === null)>Select a role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected(old('role') === $role)>
                                    {{ $role }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button class="mt-px min-h-10.25 w-full cursor-pointer rounded-lg border-0 bg-[#2161f3] text-sm font-semibold text-white transition-[background-color,transform] duration-150 hover:bg-[#174ed4] active:translate-y-px focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-blue-600/30 max-[520px]:min-h-11.5" type="submit">Sign In</button>
                </form>

                <p class="mt-6.5 mb-0 text-center text-xs text-[#91a3c1]">No real transactions processed</p>
            </div>
        </section>
    </main>
</body>
</html>
