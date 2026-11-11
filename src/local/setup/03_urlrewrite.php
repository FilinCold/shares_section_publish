<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Правило маршрутизации /actions/ -> bitrix:news. Идемпотентно.
require __DIR__ . '/lib.php';
bxBoot();

$cond = '#^/actions/#';
$found = CUrlRewriter::GetList(['SITE_ID' => 's1', 'CONDITION' => $cond]);

if ($found) {
    logLine('OK правило ' . $cond . ' уже есть');
} else {
    CUrlRewriter::Add([
        'SITE_ID'   => 's1',
        'CONDITION' => $cond,
        'RULE'      => '',
        'ID'        => 'bitrix:news',
        'PATH'      => '/actions/index.php',
    ]);
    logLine('OK правило ' . $cond . ' добавлено');
}

$check = CUrlRewriter::GetList(['SITE_ID' => 's1', 'CONDITION' => $cond]);
if (count($check) !== 1) {
    logLine('FAIL правил с условием ' . $cond . ': ' . count($check));
    exit(1);
}
logLine('ИТОГ: всё OK');
