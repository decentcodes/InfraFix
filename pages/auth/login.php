<?php
require_once '../../includes/auth.php';

if (isCitizenLoggedIn()) {
    header('Location: ../citizen/home.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login • InfraFix</title>

    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>

    <main class="auth-page">

        <div class="auth-container">

            <a href="../../index.php" class="auth-logo">
                InfraFix
            </a>

            <div class="auth-card">

                <div class="auth-header">
                    <h1>Welcome back</h1>
                    <p>Continue with your phone number</p>
                </div>

                <form id="phone-form">
                    <div class="form-group">
                        <label for="phone">Phone number</label>
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            placeholder="Enter 10-digit mobile number"
                            inputmode="numeric"
                            maxlength="10"
                            autocomplete="tel"
                            required>
                    </div>

                    <button type="submit" class="primary-button">Continue</button>
                </form>

                <div id="auth-flow"></div>

                <p id="auth-message" class="auth-message"></p>

            </div>

        </div>

    </main>

    <script>
        const phoneForm = document.getElementById('phone-form');
        const authFlow = document.getElementById('auth-flow');
        const authMessage = document.getElementById('auth-message');

        phoneForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const phone = document.getElementById('phone').value.trim();

            authMessage.textContent = '';
            authFlow.innerHTML = '';

            try {
                const response = await fetch('../../api/auth/check-phone.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        phone: phone
                    })
                });

                const data = await response.json();

                if (!data.success) {
                    authMessage.textContent = data.message;
                    return;
                }

                if (data.is_existing_citizen) {
                    showPasswordStep(phone);
                } else {
                    showOtpStep(phone);
                }

            } catch (error) {
                console.error(error);
                authMessage.textContent = 'Something went wrong. Please try again.';
            }
        });

        function showPasswordStep(phone) {
            phoneForm.style.display = 'none';

            authFlow.innerHTML = `
        <div class="auth-header">
            <h2>Enter your password</h2>
            <p>Continue with your InfraFix account</p>
        </div>

        <form id="password-form">
            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="primary-button">
                Login
            </button>
        </form>
    `;

            document.getElementById('password-form').addEventListener('submit', async function(event) {
                event.preventDefault();

                const password = document.getElementById('password').value;

                authMessage.textContent = '';

                try {
                    const response = await fetch('../../api/auth/login.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            phone: phone,
                            password: password
                        })
                    });

                    const data = await response.json();

                    if (!data.success) {
                        authMessage.textContent = data.message;
                        return;
                    }

                    window.location.href = '../citizen/home.php';

                } catch (error) {
                    console.error(error);
                    authMessage.textContent = 'Something went wrong. Please try again.';
                }
            });
        }

        async function showOtpStep(phone) {
            phoneForm.style.display = 'none';

            authMessage.textContent = 'Sending verification code...';

            try {
                const response = await fetch('../../api/auth/send-otp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        phone: phone
                    })
                });

                const data = await response.json();

                if (!data.success) {
                    authMessage.textContent = data.message;
                    phoneForm.style.display = 'block';
                    return;
                }

                authMessage.textContent = '';

                authFlow.innerHTML = `
            <div class="auth-header">
                <h2>Verify your phone</h2>
                <p>We sent a verification code to your phone.</p>
            </div>

            <form id="otp-form">
                <div class="form-group">
                    <label for="otp">OTP</label>
                    <input
                        type="text"
                        id="otp"
                        name="otp"
                        placeholder="Enter 6-digit OTP"
                        inputmode="numeric"
                        maxlength="6"
                        autocomplete="one-time-code"
                        required
                    >
                </div>

                <button type="submit" class="primary-button">
                    Verify
                </button>
            </form>
        `;

                document.getElementById('otp-form').addEventListener('submit', async function(event) {
                    event.preventDefault();

                    const otp = document.getElementById('otp').value.trim();

                    authMessage.textContent = 'Verifying...';

                    try {
                        const response = await fetch('../../api/auth/verify-otp.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                phone: phone,
                                otp: otp
                            })
                        });

                        const data = await response.json();

                        if (!data.success) {
                            authMessage.textContent = data.message;
                            return;
                        }

                        authMessage.textContent = '';

                        showPasswordSetupStep(phone);

                    } catch (error) {
                        console.error(error);
                        authMessage.textContent =
                            'Something went wrong while verifying the OTP.';
                    }
                });

                function showPasswordSetupStep(phone) {
                    authFlow.innerHTML = `
        <div class="auth-header">
            <h2>Create your password</h2>
            <p>Set a password for your InfraFix account.</p>
        </div>

        <form id="password-setup-form">
            <div class="form-group">
                <label for="new-password">Password</label>
                <input
                    type="password"
                    id="new-password"
                    name="password"
                    placeholder="Create a password"
                    autocomplete="new-password"
                    required
                >
            </div>

            <div class="form-group">
                <label for="confirm-password">Confirm password</label>
                <input
                    type="password"
                    id="confirm-password"
                    name="confirm_password"
                    placeholder="Re-enter your password"
                    autocomplete="new-password"
                    required
                >
            </div>

            <button type="submit" class="primary-button">
                Create account
            </button>
        </form>
    `;

                    document.getElementById('password-setup-form').addEventListener('submit', async function(event) {
                        event.preventDefault();

                        const password = document.getElementById('new-password').value;
                        const confirmPassword = document.getElementById('confirm-password').value;

                        authMessage.textContent = 'Creating your account...';

                        try {
                            const response = await fetch('../../api/auth/create-account.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: new URLSearchParams({
                                    password: password,
                                    confirm_password: confirmPassword
                                })
                            });

                            const data = await response.json();

                            if (!data.success) {
                                authMessage.textContent = data.message;
                                return;
                            }

                            console.log('Created citizen ID:', data.citizen_id);

                            window.location.href = '../citizen/home.php';

                        } catch (error) {
                            console.error(error);
                            authMessage.textContent =
                                'Something went wrong while creating your account.';
                        }
                    });
                }

                console.log('Development OTP:', data.development_otp);

            } catch (error) {
                console.error(error);

                authMessage.textContent =
                    'Something went wrong while sending the verification code.';

                phoneForm.style.display = 'block';
            }
        }
    </script>
</body>

</html>