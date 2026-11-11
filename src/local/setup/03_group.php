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

// Уровни прав модулей (сверены с GetModuleTasks() модулей на сервере).
// main P = только свой профиль (достаточно для входа в админку; без просмотра пользователей/групп/настроек).
// iblock: на уровне модуля права не даём (W/X = полное управление, это системные настройки);
// работа с акциями идёт через права на сам инфоблок (W) из шага 2.
const RIGHT_MAIN   = 'P';
// Временный файл пароля внутри контейнера; на хосте root переносит его в /opt/bitrix/secrets.
const SECRET_TMP   = '/tmp/cm_test.secret';

// 1. Группа
$g = CGroup::GetList('id', 'asc', ['STRING_ID' => 'content_managers'])->Fetch();
if ($g) {
    $groupId = (int)$g['ID'];
    logLine('OK группа content_managers уже есть, ID=' . $groupId);
} else {
    $obGroup = new CGroup();
    $groupId = (int)$obGroup->Add([
        'ACTIVE'      => 'Y',
        'C_SORT'      => 200,
        'NAME'        => 'Контент-менеджеры',
        'STRING_ID'   => 'content_managers',
        'DESCRIPTION' => 'Редакторы раздела «Акции»',
    ]);
    if ($groupId <= 0) {
        $fail('создание группы', $obGroup->LAST_ERROR);
    }
    logLine('OK группа content_managers создана, ID=' . $groupId);
}

// 2. Права на инфоблок actions (группы 1 и 2 сохраняют X и R)
$ib = CIBlock::GetList([], ['CODE' => 'actions', 'CHECK_PERMISSIONS' => 'N'])->Fetch();
if (!$ib) {
    $fail('инфоблок actions не найден');
}
$iblockId = (int)$ib['ID'];
$perms = CIBlock::GetGroupPermissions($iblockId);
if (($perms[$groupId] ?? '') !== 'W') {
    $perms[$groupId] = 'W';
    CIBlock::SetPermission($iblockId, $perms);
    logLine('OK права инфоблока ' . $iblockId . ': группа ' . $groupId . ' = W');
} else {
    logLine('OK права инфоблока: группа ' . $groupId . ' уже W');
}

// 3. Права на модули
foreach (['main' => RIGHT_MAIN] as $module => $level) {
    $cur = $GLOBALS['APPLICATION']->GetGroupRight($module, [$groupId]);
    if ($cur !== $level) {
        $GLOBALS['APPLICATION']->SetGroupRight($module, $groupId, $level);
        logLine('OK права модуля ' . $module . ' = ' . $level);
    } else {
        logLine('OK права модуля ' . $module . ' уже ' . $level);
    }
}

// 3b. Доступ к папке админки /bitrix/admin/ скриптом НЕ выдаётся (системный /bitrix/.access.php не трогаем).
// Выдаётся вручную в админке: Контент → Структура сайта → Файлы и папки → bitrix → строка admin
// → меню строки «Права доступа» → группа «Контент-менеджеры» = «Чтение» → «Сохранить».
// Здесь только проверка (чтение), без записи.
$adminPerm = $GLOBALS['APPLICATION']->GetFileAccessPermission(['s1', '/bitrix/admin/'], ['G' . $groupId]);
if ($adminPerm === 'R') {
    logLine('OK файловый доступ /bitrix/admin/: группа ' . $groupId . ' = R');
} else {
    logLine('WARN файловый доступ /bitrix/admin/ у группы ' . $groupId . ' = ' . $adminPerm . ': выдать вручную в админке (см. комментарий в скрипте)');
}

// 4. Пользователь cm_test
$u = CUser::GetByLogin('cm_test')->Fetch();
$obUser = new CUser();
if ($u) {
    $userId = (int)$u['ID'];
    $groups = CUser::GetUserGroup($userId);
    if (!in_array($groupId, array_map('intval', $groups), true)) {
        $groups[] = $groupId;
        $obUser->Update($userId, ['GROUP_ID' => $groups]);
        logLine('OK cm_test добавлен в группу');
    } else {
        logLine('OK cm_test уже в группе');
    }
} else {
    $password = bin2hex(random_bytes(12)) . 'Aa1!';
    $userId = (int)$obUser->Add([
        'LOGIN'            => 'cm_test',
        'NAME'             => 'Тестовый',
        'LAST_NAME'        => 'Контент-менеджер',
        'EMAIL'            => 'cm_test@example.invalid',
        'ACTIVE'           => 'Y',
        'PASSWORD'         => $password,
        'CONFIRM_PASSWORD' => $password,
        'GROUP_ID'         => [$groupId],
        'LID'              => 's1',
    ]);
    if ($userId <= 0) {
        $fail('создание cm_test', $obUser->LAST_ERROR);
    }
    $old = umask(0177);
    $written = file_put_contents(SECRET_TMP, $password . PHP_EOL);
    umask($old);
    unset($password);
    if ($written === false) {
        $fail('запись временного файла пароля');
    }
    chmod(SECRET_TMP, 0600);
    logLine('OK cm_test создан, ID=' . $userId . '; пароль записан во временный файл ' . SECRET_TMP);
}

// 5. Итоговая проверка
$cnt = 0;
$rs = CGroup::GetList('id', 'asc', ['STRING_ID' => 'content_managers']);
while ($rs->Fetch()) { $cnt++; }
if ($cnt !== 1) {
    $fail('групп content_managers: ' . $cnt);
}
$cnt = 0;
$rs = CUser::GetList('id', 'asc', ['LOGIN_EQUAL' => 'cm_test']);
while ($rs->Fetch()) { $cnt++; }
if ($cnt !== 1) {
    $fail('пользователей cm_test: ' . $cnt);
}
logLine('INFO GROUP_ID=' . $groupId . ' USER_ID=' . $userId . ' IBLOCK_ID=' . $iblockId);
logLine('ИТОГ: всё OK');
exit(0);
