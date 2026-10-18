<?php

require_once __DIR__ . '/bootstrap.php';

// Harden session handling before PHP creates or resumes the session cookie.
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

/** Return the session-bound CSRF token, creating one when needed. */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Validate a submitted CSRF token using a constant-time comparison. */
function isValidCsrfToken($submittedToken): bool
{
    return is_string($submittedToken)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}

/** Rotate the CSRF token when the session's privilege level changes. */
function rotateCsrfToken(): string
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}


function setOtpVerifiedPhone($phone)
{
    $_SESSION['otp_verified_phone'] = $phone;
    $_SESSION['otp_verified_at'] = time();
}

function getOtpVerifiedPhone()
{
    if (
        !isset($_SESSION['otp_verified_phone']) ||
        !isset($_SESSION['otp_verified_at'])
    ) {
        return null;
    }

    // OTP verification state is valid for 10 minutes.
    if (time() - $_SESSION['otp_verified_at'] > 600) {
        clearOtpVerifiedPhone();
        return null;
    }

    return $_SESSION['otp_verified_phone'];
}

function clearOtpVerifiedPhone()
{
    unset(
        $_SESSION['otp_verified_phone'],
        $_SESSION['otp_verified_at']
    );
}


function loginCitizen($citizen)
{
    session_regenerate_id(true);
    rotateCsrfToken();

    $_SESSION['citizen_id'] = $citizen['id'];
    $_SESSION['citizen_code'] = $citizen['citizen_id'];
}

function isCitizenLoggedIn()
{
    return isset($_SESSION['citizen_id']);
}

function getLoggedInCitizenId()
{
    return $_SESSION['citizen_id'] ?? null;
}

function logoutCitizen()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function requireCitizenAuth()
{
    if (!isCitizenLoggedIn()) {
        header('Location: ../../pages/auth/login.php');
        exit;
    }
}

// Admin

function loginAdmin($admin)
{
    session_regenerate_id(true);
    rotateCsrfToken();

    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_code'] = $admin['admin_id'];
}

function isAdminLoggedIn()
{
    return isset($_SESSION['admin_id']);
}

function getLoggedInAdminId()
{
    return $_SESSION['admin_id'] ?? null;
}

function logoutAdmin()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function requireAdminAuth()
{
    if (!isAdminLoggedIn()) {
        header('Location: ../../pages/admin/login.php');
        exit;
    }
}

function loginAuthority($authority)
{
    session_regenerate_id(true);
    rotateCsrfToken();

    $_SESSION['authority_id'] = $authority['id'];
    $_SESSION['authority_code'] = $authority['authority_id'];
}


function isAuthorityLoggedIn()
{
    return isset($_SESSION['authority_id']);
}


function getLoggedInAuthorityId()
{
    return $_SESSION['authority_id'] ?? null;
}


function logoutAuthority()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}


function requireAuthorityAuth()
{
    if (!isAuthorityLoggedIn()) {

        header(
            'Location: ../../pages/authority/login.php'
        );

        exit;
    }
}