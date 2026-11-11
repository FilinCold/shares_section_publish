<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/lib.php';
bxBoot();

use Bitrix\Main\Loader;

if (!Loader::includeModule('iblock')) {
    logLine('FAIL модуль iblock не подключён');
    exit(1);
}

$fail = static function (string $what, string $err = ''): void {
    logLine('FAIL ' . $what . ($err !== '' ? ': ' . $err : ''));
    exit(1);
};

$ib = CIBlock::GetList([], ['CODE' => 'actions', 'CHECK_PERMISSIONS' => 'N'])->Fetch();
if (!$ib) {
    $fail('инфоблок actions не найден (сначала 01_iblock.php)');
}
$iblockId = (int)$ib['ID'];
logLine('INFO IBLOCK_ID=' . $iblockId);

$jsonPath = __DIR__ . '/seed-data.json';
$items = json_decode((string)@file_get_contents($jsonPath), true);
if (!is_array($items) || count($items) !== 3) {
    $fail('seed-data.json не читается или не 3 элемента');
}

$now = time();
$el = new CIBlockElement();
$bad = 0;

foreach ($items as $it) {
    $code = (string)$it['code'];
    $from = ConvertTimeStamp($now + (int)$it['days_from'] * 86400, 'FULL');
    $to = ConvertTimeStamp($now + (int)$it['days_to'] * 86400, 'FULL');

    $fields = [
        'IBLOCK_ID'    => $iblockId,
        'NAME'         => $it['name'],
        'CODE'         => $code,
        'ACTIVE'       => 'Y',
        'ACTIVE_FROM'  => $from,
        'ACTIVE_TO'    => $to,
        'PREVIEW_TEXT' => $it['preview_text'],
        'PREVIEW_TEXT_TYPE' => 'text',
        'DETAIL_TEXT'  => $it['detail_text'],
        'DETAIL_TEXT_TYPE' => 'text',
    ];

    $existing = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $iblockId, 'CODE' => $code],
        false,
        false,
        ['ID', 'PREVIEW_PICTURE', 'DETAIL_PICTURE']
    )->Fetch();

    // Картинки: грузим только если их ещё нет (повторный запуск не плодит файлы)
    if (!$existing || !(int)$existing['PREVIEW_PICTURE']) {
        $fields['PREVIEW_PICTURE'] = CFile::MakeFileArray(__DIR__ . '/' . $it['preview_image']);
    }
    if (!$existing || !(int)$existing['DETAIL_PICTURE']) {
        $fields['DETAIL_PICTURE'] = CFile::MakeFileArray(__DIR__ . '/' . $it['detail_image']);
    }

    if ($existing) {
        $id = (int)$existing['ID'];
        if (!$el->Update($id, $fields)) {
            $fail('обновление ' . $code, $el->LAST_ERROR);
        }
        CIBlockElement::SetPropertyValuesEx($id, $iblockId, ['DISCOUNT' => $it['discount']]);
        $verb = 'обновлена';
    } else {
        $fields['PROPERTY_VALUES'] = ['DISCOUNT' => $it['discount']];
        $id = (int)$el->Add($fields);
        if ($id <= 0) {
            $fail('создание ' . $code, $el->LAST_ERROR);
        }
        $verb = 'создана';
    }

    $row = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $iblockId, 'ID' => $id],
        false,
        false,
        ['ID', 'CODE', 'ACTIVE', 'ACTIVE_FROM', 'ACTIVE_TO', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'PROPERTY_DISCOUNT']
    )->Fetch();
    $okRow = $row && $row['ACTIVE'] === 'Y' && (int)$row['PREVIEW_PICTURE'] > 0
        && (int)$row['DETAIL_PICTURE'] > 0 && (string)$row['PROPERTY_DISCOUNT_VALUE'] !== '';
    if (!$okRow) {
        $bad++;
    }
    logLine(($okRow ? 'OK' : 'FAIL') . ' акция ' . $verb . ' CODE=' . $code . ' ID=' . $id
        . ' ACTIVE_FROM=' . $row['ACTIVE_FROM'] . ' ACTIVE_TO=' . $row['ACTIVE_TO']
        . ' DISCOUNT=' . $row['PROPERTY_DISCOUNT_VALUE']);
}

$cnt = (int)CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId], []);
logLine(($cnt === 3 ? 'OK' : 'FAIL') . ' элементов в инфоблоке: ' . $cnt);
if ($cnt !== 3) {
    $bad++;
}

logLine($bad === 0 ? 'ИТОГ: всё OK' : 'ИТОГ: FAIL x' . $bad);
exit($bad === 0 ? 0 : 1);
