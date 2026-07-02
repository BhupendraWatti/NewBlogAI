<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - {{ config('app.name', 'NewsBlogify') }}</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --bg-primary: #04080e;
            --bg-surface: rgba(10, 20, 32, 0.6);
            --border-color: rgba(16, 185, 129, 0.2);
            --border-hover: rgba(16, 185, 129, 0.4);
            --accent: #10b981; /* Emerald neon */
            --accent-glow: rgba(16, 185, 129, 0.15);
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-primary);
            background-image: 
                radial-gradient(at 0% 0%, rgba(16, 185, 129, 0.08) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(59, 130, 246, 0.05) 0px, transparent 50%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            overflow-x: hidden;
        }

        .login-card {
            background: var(--bg-surface);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            width: 100%;
            max-width: 440px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7), 0 0 40px -10px var(--accent-glow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            border-radius: 24px 24px 0 0;
        }

        .login-card:hover {
            border-color: var(--border-hover);
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.8), 0 0 50px -5px rgba(16, 185, 129, 0.25);
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 54px;
            height: 54px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            color: var(--accent);
            margin-bottom: 1.25rem;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.2);
        }

        .logo-container span {
            font-size: 28px;
        }

        .title {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #ffffff 0%, #a7f3d0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            font-family: 'JetBrains Mono', monospace;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 20px;
            pointer-events: none;
            transition: color 0.2s;
        }

        .form-input {
            width: 100%;
            background: rgba(4, 8, 14, 0.8);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            color: var(--text-main);
            font-size: 0.9rem;
            font-family: inherit;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.15);
            background: rgba(4, 8, 14, 0.95);
        }

        .form-input:focus + .input-icon {
            color: var(--accent);
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            cursor: pointer;
            font-size: 20px;
            user-select: none;
            transition: color 0.2s;
        }

        .password-toggle:hover {
            color: var(--accent);
        }

        .btn-submit {
            width: 100%;
            background: var(--accent);
            color: #04080e;
            border: none;
            border-radius: 12px;
            padding: 0.85rem;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-submit:hover {
            background: #34d399;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* Spinner for loading state */
        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(4, 8, 14, 0.3);
            border-radius: 50%;
            border-top-color: #04080e;
            animation: spin 0.6s linear infinite;
            display: none;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-submit.loading .spinner {
            display: inline-block;
        }

        .btn-submit.loading .btn-text {
            visibility: hidden;
            width: 0;
            height: 0;
            overflow: hidden;
        }

        .footer-note {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-muted);
            font-family: 'JetBrains Mono', monospace;
        }

        .footer-note a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }

        .footer-note a:hover {
            text-decoration: underline;
        }

        /* SweetAlert custom styling override for dark mode consistency */
        .swal2-popup {
            background: #0a1420 !important;
            border: 1px solid var(--border-color) !important;
            border-radius: 20px !important;
            color: var(--text-main) !important;
        }
        .swal2-title {
            color: var(--text-main) !important;
        }
        .swal2-html-container {
            color: var(--text-muted) !important;
        }
        .swal2-confirm {
            background-color: var(--accent) !important;
            color: #04080e !important;
            border-radius: 10px !important;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="header">
            <div class="logo-container">
                <span class="material-symbols-outlined">neurology</span>
            </div>
            <h2 class="title">Welcome Back</h2>
            <p class="subtitle">Log in to manage your automated content pipeline</p>
        </div>

        <form id="loginForm" autocomplete="off">
            @csrf
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <div class="input-wrapper">
                    <input class="form-input" type="email" id="email" required placeholder="admin@newsblogify.com" value="admin@newsblogify.com">
                    <span class="material-symbols-outlined input-icon">mail</span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-wrapper">
                    <input class="form-input" type="password" id="password" required placeholder="••••••••" value="admin123">
                    <span class="material-symbols-outlined input-icon">lock</span>
                    <span class="material-symbols-outlined password-toggle" id="passwordToggle" onclick="togglePasswordVisibility()">visibility</span>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <div class="spinner"></div>
                <span class="btn-text">Authenticate Credentials</span>
            </button>
        </form>

        <div class="footer-note">
            Protected by SaaS Sentinel Security. <a href="/">Return Home</a>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('passwordToggle');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.innerText = 'visibility_off';
            } else {
                passwordField.type = 'password';
                toggleIcon.innerText = 'visibility';
            }
        }

        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const submitBtn = document.getElementById('submitBtn');
            
            // Set loading state
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;

            try {
                const response = await fetch('/api/v1/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    },
                    body: JSON.stringify({ email, password })
                });

                const result = await response.json();

                if (response.ok) {
                    // Success alert
                    Swal.fire({
                        icon: 'success',
                        title: 'Authentication Successful',
                        text: 'Redirecting to your dashboard workspace...',
                        showConfirmButton: false,
                        timer: 1500,
                        willClose: () => {
                            window.location.href = '/';
                        }
                    });
                } else {
                    // Error response handling
                    Swal.fire({
                        icon: 'error',
                        title: 'Authentication Failed',
                        text: result.message || 'Invalid credentials or user not found.',
                        confirmButtonText: 'Retry'
                    });
                }
            } catch (err) {
                console.error('Login error:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'System Error',
                    text: 'Unable to communicate with the Auth server. Please try again.',
                    confirmButtonText: 'Okay'
                });
            } finally {
                // Remove loading state
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
        });
    </script>
</body>
</html>
