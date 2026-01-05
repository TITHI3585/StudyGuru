<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/database.php';
$oauthConfig = require __DIR__ . '/config/oauth.php';

$alreadyAuthenticated = isset($_SESSION['auth_user']['id']);
if ($alreadyAuthenticated && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$mode = isset($_GET['mode']) && $_GET['mode'] === 'signup' ? 'signup' : 'signin';

$signupErrors = [];
$signinErrors = [];
$successMessage = null;
$signupData = ['full_name' => '', 'email' => ''];
$signinData = ['email' => ''];
$pdo = null;

$oauthError = $_SESSION['oauth_error'] ?? null;
if ($oauthError) {
    $signinErrors[] = $oauthError;
    unset($_SESSION['oauth_error']);
}

if (empty($_SESSION['google_oauth_state'])) {
    $_SESSION['google_oauth_state'] = bin2hex(random_bytes(16));
}
$googleState = $_SESSION['google_oauth_state'];

$googleClientId = $oauthConfig['google']['client_id'] ?? '';
$googleClientSecret = $oauthConfig['google']['client_secret'] ?? '';
$googleRedirectUri = $oauthConfig['google']['redirect_uri'] ?? '';
$googleOAuthReady = $googleClientId && $googleClientSecret && $googleRedirectUri;
$googleOAuthNotice = $googleOAuthReady ? null : 'Google sign-in is not configured. Update config/oauth.php with your client ID, secret, and redirect URI.';
if ($googleOAuthReady) {
    $googleAuthParams = [
        'client_id' => $googleClientId,
        'redirect_uri' => $googleRedirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'access_type' => 'offline',
        'include_granted_scopes' => 'true',
        'prompt' => 'select_account',
        'state' => $googleState
    ];
    $googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($googleAuthParams);
} else {
    $googleAuthUrl = '#';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'signin';
    $mode = $action === 'signup' ? 'signup' : 'signin';

    if ($action === 'signup') {
        $signupData['full_name'] = trim($_POST['full_name'] ?? '');
        $signupData['email'] = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $termsAccepted = isset($_POST['terms']);

        if ($signupData['full_name'] === '') {
            $signupErrors[] = 'Full name is required.';
        }

        if (!filter_var($signupData['email'], FILTER_VALIDATE_EMAIL)) {
            $signupErrors[] = 'A valid email address is required.';
        }

        if (strlen($password) < 8) {
            $signupErrors[] = 'Password must be at least 8 characters long.';
        }

        if (!$termsAccepted) {
            $signupErrors[] = 'Please agree to the StudyGuru terms and privacy policy.';
        }

        if (empty($signupErrors)) {
            try {
                if (!$pdo instanceof PDO) {
                    $pdo = getDatabaseConnection();
                }
            } catch (Throwable $exception) {
                $signupErrors[] = 'Database connection failed. Please try again shortly.';
            }
        }

        if (empty($signupErrors)) {
            $lookupUser = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $lookupUser->execute(['email' => $signupData['email']]);

            if ($lookupUser->fetch()) {
                $signupErrors[] = 'An account already exists for this email. Try signing in instead.';
            }
        }

        if (empty($signupErrors)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $createUser = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, provider) VALUES (:full_name, :email, :password_hash, :provider)');
            $createUser->execute([
                'full_name' => $signupData['full_name'],
                'email' => $signupData['email'],
                'password_hash' => $passwordHash,
                'provider' => 'email'
            ]);

            $successMessage = 'Account created successfully. You can sign in now.';
            $signupData = ['full_name' => '', 'email' => ''];
            $mode = 'signin';
        }
    } elseif ($action === 'signin') {
        $signinData['email'] = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!filter_var($signinData['email'], FILTER_VALIDATE_EMAIL)) {
            $signinErrors[] = 'Enter the email you used to sign up.';
        }

        if ($password === '') {
            $signinErrors[] = 'Enter your password to continue.';
        }

        if (empty($signinErrors)) {
            try {
                if (!$pdo instanceof PDO) {
                    $pdo = getDatabaseConnection();
                }
            } catch (Throwable $exception) {
                $signinErrors[] = 'Database connection failed. Please try again shortly.';
            }
        }

        if (empty($signinErrors)) {
            $findUser = $pdo->prepare('SELECT id, full_name, email, password_hash, provider FROM users WHERE email = :email LIMIT 1');
            $findUser->execute(['email' => $signinData['email']]);
            $user = $findUser->fetch();

            if (!$user) {
                $signinErrors[] = 'No account was found for that email.';
            } elseif ($user['provider'] !== 'email') {
                $signinErrors[] = 'This email is linked to Google sign-in. Continue with Google instead.';
            } elseif (!password_verify($password, $user['password_hash'] ?? '')) {
                $signinErrors[] = 'Incorrect password. Try again or reset it.';
            } else {
                $_SESSION['auth_user'] = [
                    'id' => (int) $user['id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email']
                ];

                $updateLogin = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
                $updateLogin->execute(['id' => $user['id']]);

                header('Location: dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Access your StudyGuru account to continue adaptive study planning with AI-driven schedules and accountability.">
    <title>StudyGuru | Access your workspace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <nav class="nav auth-nav">
        <div class="logo"><a href="index.php">StudyGuru</a></div>
        <div class="nav-actions">
            <a class="ghost" href="index.php#workflow">Back to overview</a>
            <a class="solid" href="index.php#planner">Build a plan</a>
        </div>
    </nav>

    <main class="auth-page">
        <div class="auth-left">
            <p class="eyebrow">Seamless entry</p>
            <h1>Command your adaptive study cockpit.</h1>
            <p>One login keeps every syllabus ingest, focus streak, and accountability nudge in sync across devices.</p>
            <ul class="auth-perks">
                <li>Resume the exact block, notes, or quiz you paused.</li>
                <li>Share live progress with mentors or cohorts instantly.</li>
                <li>Receive proactive recovery reminders before burnout hits.</li>
            </ul>
        </div>
        <div class="auth-panel <?php echo $mode === 'signup' ? 'is-signup' : ''; ?>" data-mode="<?php echo $mode; ?>">
            <div class="auth-tabs">
                <button type="button" class="auth-tab <?php echo $mode === 'signin' ? 'is-active' : ''; ?>" data-target="signin">Sign in</button>
                <button type="button" class="auth-tab <?php echo $mode === 'signup' ? 'is-active' : ''; ?>" data-target="signup">Sign up</button>
            </div>
            <?php if ($successMessage): ?>
                <div class="auth-alert auth-alert--success">
                    <?php echo htmlspecialchars($successMessage, ENT_QUOTES); ?>
                </div>
            <?php endif; ?>
            <div class="auth-cards">
                <div class="auth-card auth-card--signin <?php echo $mode === 'signin' ? 'is-active' : ''; ?>" data-mode="signin">
                    <form class="auth-form" method="POST" action="auth.php?mode=signin">
                        <input type="hidden" name="action" value="signin">
                        <p class="eyebrow">Sign in</p>
                        <h2>Welcome back, keep your adaptive streak alive.</h2>
                        <?php if ($signinErrors): ?>
                            <div class="auth-alert auth-alert--error">
                                <ul>
                                    <?php foreach ($signinErrors as $error): ?>
                                        <li><?php echo htmlspecialchars($error, ENT_QUOTES); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <a class="auth-oauth auth-oauth--google" href="<?php echo htmlspecialchars($googleAuthUrl, ENT_QUOTES); ?>" rel="noopener">
                            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path fill="#EA4335" d="M12 10.2v3.9h5.4c-.24 1.26-.97 2.32-2.07 3.04l3.35 2.6c1.95-1.8 3.08-4.45 3.08-7.64 0-.73-.06-1.43-.18-2.1z" />
                                <path fill="#34A853" d="M6.64 14.32l-.99.76-2.67 2.07C4.27 19.88 7.89 22 12 22c2.7 0 4.97-.9 6.63-2.43l-3.35-2.6c-.9.6-2.05.97-3.28.97-2.53 0-4.68-1.7-5.44-4.03z" />
                                <path fill="#4A90E2" d="M3 7.85A9.97 9.97 0 0 0 2 12c0 1.57.36 3.06 1.01 4.38L6.64 14.3A5.96 5.96 0 0 1 6.2 12c0-.73.13-1.44.36-2.08z" />
                                <path fill="#FBBC05" d="M12 5.9c1.47 0 2.78.5 3.82 1.47l2.86-2.86C16.96 2.92 14.69 2 12 2 7.89 2 4.27 4.12 2.98 7.85L6.57 9.9C7.33 7.57 9.48 5.9 12 5.9z" />
                            </svg>
                            Continue with Google
                        </a>
                        <?php if ($googleOAuthNotice): ?>
                            <p class="auth-hint auth-hint--warning"><?php echo htmlspecialchars($googleOAuthNotice, ENT_QUOTES); ?></p>
                        <?php endif; ?>
                        <div class="auth-divider" aria-hidden="true">
                            <span></span>
                            <p>or</p>
                            <span></span>
                        </div>
                        <label>
                            Email address
                            <input type="email" name="email" placeholder="you@studyguru.com" value="<?php echo htmlspecialchars($signinData['email'] ?? '', ENT_QUOTES); ?>" required>
                        </label>
                        <label>
                            Password
                            <input type="password" name="password" placeholder="********" required>
                        </label>
                        <div class="auth-actions">
                            <label class="auth-remember">
                                <input type="checkbox" name="remember" value="1">
                                Keep me signed in
                            </label>
                            <a href="mailto:hello@studyguru.ai?subject=Reset%20my%20StudyGuru%20password">Forgot password?</a>
                        </div>
                        <button type="submit">Sign in</button>
                        <p class="auth-hint">Need an account? <a href="#" data-switch="signup">Create one</a>.</p>
                    </form>
                </div>
                <div class="auth-card auth-card--signup <?php echo $mode === 'signup' ? 'is-active' : ''; ?>" data-mode="signup">
                    <form class="auth-form" method="POST" action="auth.php?mode=signup">
                        <input type="hidden" name="action" value="signup">
                        <p class="eyebrow">Sign up</p>
                        <h2>Launch a personalized study cockpit in under a minute.</h2>
                        <?php if ($signupErrors): ?>
                            <div class="auth-alert auth-alert--error">
                                <ul>
                                    <?php foreach ($signupErrors as $error): ?>
                                        <li><?php echo htmlspecialchars($error, ENT_QUOTES); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <a class="auth-oauth auth-oauth--google" href="<?php echo htmlspecialchars($googleAuthUrl, ENT_QUOTES); ?>" rel="noopener">
                            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path fill="#EA4335" d="M12 10.2v3.9h5.4c-.24 1.26-.97 2.32-2.07 3.04l3.35 2.6c1.95-1.8 3.08-4.45 3.08-7.64 0-.73-.06-1.43-.18-2.1z" />
                                <path fill="#34A853" d="M6.64 14.32l-.99.76-2.67 2.07C4.27 19.88 7.89 22 12 22c2.7 0 4.97-.9 6.63-2.43l-3.35-2.6c-.9.6-2.05.97-3.28.97-2.53 0-4.68-1.7-5.44-4.03z" />
                                <path fill="#4A90E2" d="M3 7.85A9.97 9.97 0 0 0 2 12c0 1.57.36 3.06 1.01 4.38L6.64 14.3A5.96 5.96 0 0 1 6.2 12c0-.73.13-1.44.36-2.08z" />
                                <path fill="#FBBC05" d="M12 5.9c1.47 0 2.78.5 3.82 1.47l2.86-2.86C16.96 2.92 14.69 2 12 2 7.89 2 4.27 4.12 2.98 7.85L6.57 9.9C7.33 7.57 9.48 5.9 12 5.9z" />
                            </svg>
                            Continue with Google
                        </a>
                        <?php if ($googleOAuthNotice): ?>
                            <p class="auth-hint auth-hint--warning"><?php echo htmlspecialchars($googleOAuthNotice, ENT_QUOTES); ?></p>
                        <?php endif; ?>
                        <div class="auth-divider" aria-hidden="true">
                            <span></span>
                            <p>or</p>
                            <span></span>
                        </div>
                        <label>
                            Full name
                            <input type="text" name="full_name" placeholder="Aanya Verma" value="<?php echo htmlspecialchars($signupData['full_name'] ?? '', ENT_QUOTES); ?>" required>
                        </label>
                        <label>
                            Email address
                            <input type="email" name="email" placeholder="you@studyguru.com" value="<?php echo htmlspecialchars($signupData['email'] ?? '', ENT_QUOTES); ?>" required>
                        </label>
                        <label>
                            Create password
                            <input type="password" name="password" placeholder="At least 8 characters" required>
                        </label>
                        <label class="auth-remember">
                            <input type="checkbox" name="terms" value="1" required>
                            I agree to the StudyGuru terms and privacy policy
                        </label>
                        <button type="submit">Create account</button>
                        <p class="auth-hint">Already onboard? <a href="#" data-switch="signin">Sign in instead</a>.</p>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
