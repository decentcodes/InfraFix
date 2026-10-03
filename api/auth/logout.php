<?php

require_once '../../includes/auth.php';

logoutCitizen();

header('Location: ../../pages/auth/login.php');
exit;
