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

// 1. Тип инфоблоков content
$typeId = 'content';
$type = CIBlockType::GetByID($typeId)->Fetch();
if ($type) {
    logLine('OK тип ' . $typeId . ' уже есть');
} else {
    $obType = new CIBlockType();
    $ok = $obType->Add([
        'ID'       => $typeId,
        'SECTIONS' => 'N',
        'IN_RSS'   => 'N',
        'SORT'     => 100,
        'LANG'     => [
            'ru' => ['NAME' => 'Контент', 'SECTION_NAME' => 'Разделы', 'ELEMENT_NAME' => 'Элементы'],
            'en' => ['NAME' => 'Content', 'SECTION_NAME' => 'Sections', 'ELEMENT_NAME' => 'Elements'],
        ],
    ]);
    if (!$ok) {
        $fail('создание типа ' . $typeId, $obType->LAST_ERROR);
    }
    logLine('OK тип ' . $typeId . ' создан');
}

// 2. Инфоблок actions
$row = CIBlock::GetList([], ['CODE' => 'actions', 'CHECK_PERMISSIONS' => 'N'])->Fetch();
if ($row) {
    $iblockId = (int)$row['ID'];
    logLine('OK инфоблок actions уже есть');
} else {
    $ib = new CIBlock();
    $iblockId = (int)$ib->Add([
        'ACTIVE'          => 'Y',
        'NAME'            => 'Акции',
        'CODE'            => 'actions',
        'IBLOCK_TYPE_ID'  => $typeId,
        'SITE_ID'         => ['s1'],
        'SORT'            => 500,
        'LIST_PAGE_URL'   => '/actions/',
        'DETAIL_PAGE_URL' => '#SITE_DIR#actions/#ELEMENT_CODE#/',
        'INDEX_ELEMENT'   => 'N',
        'WORKFLOW'        => 'N',
        'BIZPROC'         => 'N',
        'VERSION'         => 1,
        'GROUP_ID'        => [1 => 'X', 2 => 'R'],
        'FIELDS'          => [
            'ACTIVE_FROM'     => ['IS_REQUIRED' => 'N'],
            'ACTIVE_TO'       => ['IS_REQUIRED' => 'N'],
            'PREVIEW_PICTURE' => ['IS_REQUIRED' => 'N'],
            'DETAIL_PICTURE'  => ['IS_REQUIRED' => 'N'],
            'PREVIEW_TEXT'    => ['IS_REQUIRED' => 'N'],
            'DETAIL_TEXT'     => ['IS_REQUIRED' => 'N'],
            'CODE'            => [
                'IS_REQUIRED'   => 'N',
                'DEFAULT_VALUE' => [
                    'TRANSLITERATION'   => 'Y',
                    'UNIQUE'            => 'Y',
                    'TRANS_CASE'        => 'L',
                    'TRANS_SPACE'       => '-',
                    'TRANS_OTHER'       => '-',
                    'TRANS_EAT'         => 'Y',
                ],
            ],
        ],
    ]);
    if ($iblockId <= 0) {
        $fail('создание инфоблока actions', $ib->LAST_ERROR);
    }
    logLine('OK инфоблок actions создан');
}
logLine('INFO IBLOCK_ID=' . $iblockId);

// 3. Свойство DISCOUNT
$prop = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'DISCOUNT'])->Fetch();
if ($prop) {
    $propId = (int)$prop['ID'];
    logLine('OK свойство DISCOUNT уже есть');
} else {
    $obProp = new CIBlockProperty();
    $propId = (int)$obProp->Add([
        'IBLOCK_ID'     => $iblockId,
        'NAME'          => 'Размер скидки / предложение',
        'CODE'          => 'DISCOUNT',
        'ACTIVE'        => 'Y',
        'SORT'          => 100,
        'PROPERTY_TYPE' => 'S',
        'MULTIPLE'      => 'N',
        'IS_REQUIRED'   => 'N',
    ]);
    if ($propId <= 0) {
        $fail('создание свойства DISCOUNT', $obProp->LAST_ERROR);
    }
    logLine('OK свойство DISCOUNT создано');
}
logLine('INFO PROPERTY_ID=' . $propId);

logLine('ИТОГ: готово');
exit(0);
