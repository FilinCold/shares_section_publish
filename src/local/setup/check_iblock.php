<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/lib.php';
bxBoot();

use Bitrix\Main\Loader;

Loader::includeModule('iblock');

$failed = 0;
function check(bool $ok, string $what): void
{
    global $failed;
    if (!$ok) {
        $failed++;
    }
    logLine(($ok ? 'OK ' : 'FAIL ') . $what);
}

$res = CIBlock::GetList([], ['CODE' => 'actions', 'CHECK_PERMISSIONS' => 'N']);
$blocks = [];
while ($row = $res->Fetch()) {
    $blocks[] = $row;
}
check(count($blocks) === 1, 'инфоблок CODE=actions ровно один (найдено: ' . count($blocks) . ')');

if (count($blocks) === 1) {
    $ib = $blocks[0];
    $id = (int)$ib['ID'];
    logLine('INFO IBLOCK_ID=' . $id);

    check($ib['ACTIVE'] === 'Y', 'ACTIVE=Y');
    check($ib['LIST_PAGE_URL'] === '/actions/', 'LIST_PAGE_URL=/actions/ (' . $ib['LIST_PAGE_URL'] . ')');
    check(
        $ib['DETAIL_PAGE_URL'] === '#SITE_DIR#actions/#ELEMENT_CODE#/',
        'DETAIL_PAGE_URL=#SITE_DIR#actions/#ELEMENT_CODE#/ (' . $ib['DETAIL_PAGE_URL'] . ')'
    );
    check(strpos((string)$ib['DETAIL_PAGE_URL'], '//') === false, 'DETAIL_PAGE_URL без //');

    $props = [];
    $pr = CIBlockProperty::GetList([], ['IBLOCK_ID' => $id, 'CODE' => 'DISCOUNT']);
    while ($p = $pr->Fetch()) {
        $props[] = $p;
    }
    check(count($props) === 1, 'свойство DISCOUNT ровно одно (найдено: ' . count($props) . ')');
    if (count($props) === 1) {
        check($props[0]['PROPERTY_TYPE'] === 'S', 'DISCOUNT тип S (' . $props[0]['PROPERTY_TYPE'] . ')');
    }

    $fields = CIBlock::GetFields($id);
    foreach (['PREVIEW_PICTURE', 'DETAIL_PICTURE', 'ACTIVE_FROM', 'ACTIVE_TO'] as $code) {
        $hidden = isset($fields[$code]['VISIBLE']) && $fields[$code]['VISIBLE'] === 'N';
        check(!$hidden, 'поле ' . $code . ' не скрыто');
    }

    $perms = CIBlock::GetGroupPermissions($id);
    check(($perms[2] ?? '') === 'R', 'группа 2 (все) = R (' . ($perms[2] ?? 'нет') . ')');
    check(($perms[1] ?? '') === 'X', 'группа 1 (админы) = X (' . ($perms[1] ?? 'нет') . ')');
}

logLine($failed === 0 ? 'ИТОГ: всё OK' : 'ИТОГ: FAIL x' . $failed);
exit($failed === 0 ? 0 : 1);
