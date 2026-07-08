<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SahamBoard · Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&family=JetBrains+Mono:wght@400;600&display=swap">
    @vite(['resources/css/app.css'])
    <style>
        .login-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 50% 0%, rgba(139,92,246,0.10), transparent 60%), var(--bg);
            padding: 1.5rem;
        }
        .login-card {
            width: 100%;
            max-width: 380px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.4);
            padding: 2.2rem 2rem;
        }
        .login-brand {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.4rem;
        }
        .login-brand .dot {
            width: 10px; height: 10px; border-radius: 50%;
            background: var(--cyan);
            box-shadow: 0 0 10px var(--cyan);
        }
        .login-brand span.name {
            font-family: var(--display);
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--ink);
        }
        .login-subtitle {
            color: var(--muted);
            font-size: 0.83rem;
            margin: 0 0 1.6rem 0;
        }
        .login-field { margin-bottom: 1rem; }
        .login-field label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .login-field input {
            width: 100%;
            box-sizing: border-box;
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--ink);
            border-radius: 8px;
            padding: 0.65rem 0.8rem;
            font-family: var(--body);
            font-size: 0.9rem;
        }
        .login-field input:focus {
            outline: none;
            border-color: var(--cyan);
        }
        .login-error {
            background: rgba(244,63,94,0.08);
            border: 1px solid rgba(244,63,94,0.3);
            color: #fda4af;
            font-size: 0.8rem;
            border-radius: 8px;
            padding: 0.6rem 0.8rem;
            margin-bottom: 1rem;
        }
        .login-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--cyan), var(--purple));
            color: #0a0e1a;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            padding: 0.7rem;
            font-size: 0.9rem;
            cursor: pointer;
            font-family: var(--body);
        }
        .login-submit:hover { opacity: 0.92; }
        .login-footer {
            margin-top: 1.4rem;
            text-align: center;
            font-family: var(--mono);
            font-size: 0.65rem;
            color: #475569;
        }
    </style>
</head>
<body>
<div class="login-shell">
    <div class="login-card">
        <div class="login-brand">
            <span class="dot"></span>
            <span class="name">SahamBoard</span>
        </div>
        <p class="login-subtitle">Masuk untuk mengakses dashboard.</p>

        @if ($errors->any())
            <div class="login-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}">
            @csrf
            <div class="login-field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="{{ old('username') }}" autofocus required>
            </div>
            <div class="login-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="login-submit">Masuk</button>
        </form>

        <div class="login-footer">IDX · EQUITIES · SCREENER</div>
    </div>
</div>
</body>
</html>
