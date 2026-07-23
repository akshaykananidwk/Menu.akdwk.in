<?php
/** Convenience redirect: /app/reception/ → the canonical /reception/ screen. */
require_once dirname(__DIR__, 2) . '/config/config.php';
redirect(BASE_URL . '/reception/index.php');
