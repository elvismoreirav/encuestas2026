<?php

require_once __DIR__ . '/bootstrap.php';

if (!Database::isInstalled()) {
    redirect('install.php');
}

if (auth()->check()) {
    redirect('dashboard.php');
}

redirect('login.php');
