<?php

require_once __DIR__ . '/bootstrap.php';

if (!Database::isInstalled()) {
    redirect('install.php');
}

redirect('public/index.php');
