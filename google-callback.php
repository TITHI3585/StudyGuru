<?php
declare(strict_types=1);

use Google\Client as GoogleClient;
use Google\Service\Oauth2 as GoogleOauthService;

session_start();

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';
$oauthConfig = require __DIR__ . '/config/oauth.php';

$googleClientId = $oauthConfig['google']['client_id'] ?? '';
$googleClientSecret = $oauthConfig['google']['client_secret'] ?? '';
$googleRedirectUri = $oauthConfig['google']['redirect_uri'] ?? '';

if (!$googleClientId || !$googleClientSecret || !$googleRedirectUri) {
    $_SESSION['oauth_error'] = 'Google sign-in is not configured. Update config/oauth.php with your client credentials.';
    header('Location: auth.php?mode=signin');
    exit;
}

$state = $_GET['state'] ?? '';
$expectedState = $_SESSION['google_oauth_state'] ?? null;

$redirectWithError = static function (string $message): void {
    $_SESSION['oauth_error'] = $message;
    header('Location: auth.php?mode=signin');
    exit;
};

if (!$state || !$expectedState || !hash_equals($expectedState, $state)) {
    $redirectWithError('The Google sign-in request expired. Please try again.');
}

unset($_SESSION['google_oauth_state']);

if (!isset($_GET['code'])) {
    $redirectWithError('Google sign-in was cancelled. Please try again.');
}

try {
    $client = new GoogleClient();
    $client->setClientId($googleClientId);
    $client->setClientSecret($googleClientSecret);
    $client->setRedirectUri($googleRedirectUri);
    $client->setAccessType('offline');
    $client->setIncludeGrantedScopes(true);
    $client->setPrompt('select_account');
    $client->setScopes(['openid', 'email', 'profile']);

    error_log('Google OAuth: fetching token...');
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        $friendly = sprintf('Unable to complete Google sign-in (%s). Please try again.', $token['error']);
        error_log('Google OAuth token error: ' . json_encode($token));
        $redirectWithError($friendly);
    }

    $client->setAccessToken($token);
    error_log('Google OAuth: token acquired. Fetching profile...');

    $oauthService = new GoogleOauthService($client);
    $googleUser = $oauthService->userinfo->get();

    if (!$googleUser || !$googleUser->getEmail()) {
        $redirectWithError('Google did not return your email address. Please try a different method.');
    }

    $googleId = $googleUser->getId();
    $fullName = $googleUser->getName() ?: trim($googleUser->getGivenName() . ' ' . $googleUser->getFamilyName()) ?: 'Pilot';
    $email = $googleUser->getEmail();
    $avatar = $googleUser->getPicture() ?: null;

    try {
        error_log('Google OAuth: connecting to database...');
        $pdo = getDatabaseConnection();
    } catch (Throwable $exception) {
        $redirectWithError('Unable to connect to the StudyGuru database. Please try again later.');
    }

    try {
        $pdo->beginTransaction();

        $lookup = $pdo->prepare('SELECT id, provider FROM users WHERE email = :email LIMIT 1');
        $lookup->execute(['email' => $email]);
        $existingUser = $lookup->fetch();

        if ($existingUser) {
            error_log('Google OAuth: updating existing user ' . $existingUser['id']);
            $update = $pdo->prepare('UPDATE users SET full_name = :full_name, google_id = :google_id, avatar_url = :avatar_url, provider = :provider, last_login_at = NOW() WHERE id = :id');
            $update->execute([
                'full_name' => $fullName,
                'google_id' => $googleId,
                'avatar_url' => $avatar,
                'provider' => 'google',
                'id' => $existingUser['id']
            ]);
            $userId = (int) $existingUser['id'];
        } else {
            error_log('Google OAuth: inserting new user for ' . $email);
            $insert = $pdo->prepare('INSERT INTO users (full_name, email, google_id, avatar_url, provider, last_login_at) VALUES (:full_name, :email, :google_id, :avatar_url, :provider, NOW())');
            $insert->execute([
                'full_name' => $fullName,
                'email' => $email,
                'google_id' => $googleId,
                'avatar_url' => $avatar,
                'provider' => 'google'
            ]);
            $userId = (int) $pdo->lastInsertId();
        }

        $pdo->commit();
    } catch (Throwable $dbException) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Google OAuth DB error: ' . $dbException->getMessage());
        throw $dbException;
    }

    $fetchUser = $pdo->prepare('SELECT id, full_name, email, provider, avatar_url FROM users WHERE id = :id LIMIT 1');
    $fetchUser->execute(['id' => $userId]);
    $userRecord = $fetchUser->fetch();

    if (!$userRecord) {
        $redirectWithError('Unable to load your StudyGuru profile after Google sign-in.');
    }

    $_SESSION['auth_user'] = [
        'id' => (int) $userRecord['id'],
        'full_name' => $userRecord['full_name'],
        'email' => $userRecord['email'],
        'provider' => $userRecord['provider'],
        'avatar_url' => $userRecord['avatar_url'] ?? null
    ];

    header('Location: dashboard.php');
    exit;
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Google OAuth error: ' . $exception->getMessage());
    error_log('Google OAuth stack: ' . $exception->getTraceAsString());
    $redirectWithError('Google sign-in failed unexpectedly. Please try again.');
}
