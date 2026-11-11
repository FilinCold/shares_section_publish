<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/**
 * Общие хелперы CLI-скриптов настройки. Только из консоли.
 */

function bxBoot(): void
{
    $_SERVER['DOCUMENT_ROOT'] = '/var/www/bitrix';
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';

    define('NO_KEEP_STATISTIC', true);
    define('NOT_CHECK_PERMISSIONS', true);
    define('BX_CLI', true);

    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
}

function logLine(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}
