<?php
/** Convenience redirect: /app/kitchen/ → the canonical /kitchen/ screen. */
require_once dirname(__DIR__, 2) . '/config/config.php';
redirect(BASE_URL . '/kitchen/index.php');
