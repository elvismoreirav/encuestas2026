<?php

require_once __DIR__ . '/bootstrap.php';

if (Database::isInstalled()) {
    auth()->logout();
}

redirect('admin/');
