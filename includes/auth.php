<?php

session_start();

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
