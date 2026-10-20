<?php

require_once __DIR__ . '/../../includes/auth.php';

if (isAuthorityLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

?>

<div class="auth-page">

    <div class="auth-card">

        <a href="../../index.php" class="auth-back-link">
            &larr; Back
        </a>

        <h1>Authority Login</h1>

        <p class="auth-subtitle">
            Sign in to manage public infrastructure issues.
        </p>


        <form id="authority-login-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">

                <label for="email">
                    Official Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    autocomplete="username"
                    required
                >

            </div>


            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Login
            </button>


            <p
                id="login-message"
                class="form-message"
            ></p>

        </form>

        <p class="auth-subtitle">
            New authority?
            <a href="request-access.php">Request access</a>
            (an administrator reviews every request).
        </p>

    </div>

</div>


<script>

document
    .getElementById('authority-login-form')
    .addEventListener('submit', async function (event) {

        event.preventDefault();

        const form = event.target;

        const message =
            document.getElementById('login-message');

        message.textContent = '';


        const formData = new FormData(form);


        try {

            const response = await fetch(
                '../../api/authority/login.php',
                {
                    method: 'POST',
                    body: formData
                }
            );


            const data = await response.json();


            if (!data.success) {

                message.textContent =
                    data.message;

                return;
            }


            window.location.href =
                'dashboard.php';


        } catch (error) {

            message.textContent =
                'Something went wrong. Please try again.';

        }

    });

</script>
