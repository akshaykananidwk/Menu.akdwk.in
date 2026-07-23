<?php
/** Convenience redirect: /app/waiter/ → the canonical /waiter/ screen. */
require_once dirname(__DIR__, 2) . '/config/config.php';
redirect(BASE_URL . '/waiter/index.php');
