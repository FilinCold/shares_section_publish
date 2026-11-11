<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/**
 * Сценарная проверка видимости акций на /actions/ (активна, скрыта, граничные даты, карточка без картинки; --expiry).
 * Запуск: php 04_visibility_check.php <домен> [--expiry]
 * Меняет акцию skidka-na-vse и гарантированно восстанавливает её (finally).
 */

require __DIR__ . '/lib.php';
bxBoot();

$args = array_slice($argv, 1);
$expiry = in_array('--expiry', $args, true);
$args = array_values(array_filter($args, fn($a) => $a !== '--expiry'));
$domain = $args[0] ?? '';
if ($domain === '' || !preg_match('/^[a-z0-9.-]+$/i', $domain)) {
    logLine('FAIL аргументы: нужен домен, например bitrix.example.com');
    exit(2);
}

use Bitrix\Main\Loader;

Loader::includeModule('iblock');
global $CACHE_MANAGER;

$fails = 0;
function check(string $name, bool $ok, string $why = ''): void
{
    global $fails;
    if ($ok) {
        logLine('OK ' . $name);
    } else {
        $fails++;
        logLine('FAIL ' . $name . ($why !== '' ? ' — ' . $why : ''));
    }
}

$iblock = CIBlock::GetList([], ['CODE' => 'actions', 'CHECK_PERMISSIONS' => 'N'])->Fetch();
if (!$iblock) { logLine('FAIL инфоблок actions не найден'); exit(1); }
$iblockId = (int)$iblock['ID'];

function clearCache(): void
{
    global $CACHE_MANAGER, $iblockId;
    $CACHE_MANAGER->ClearByTag('iblock_id_' . $iblockId);
}

function fmt(int $ts): string
{
    return ConvertTimeStamp($ts, 'FULL');
}

/** Витрина: HTTP-запрос /actions/, возвращает [код, тело]. */
function fetchShowcase(string $domain): array
{
    $ch = curl_init('https://' . $domain . '/actions/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, is_string($body) ? $body : ''];
}

function visible(string $domain, string $name): bool
{
    [$code, $body] = fetchShowcase($domain);
    return $code === 200 && strpos($body, htmlspecialchars($name, ENT_QUOTES)) !== false;
}

function pageClean(string $body): bool
{
    return !preg_match('/Fatal error|Warning:|Notice:|Parse error|&amp;#/u', $body);
}

$el = new CIBlockElement();
$a = CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'skidka-na-vse'], false, false,
    ['ID', 'NAME', 'ACTIVE', 'ACTIVE_FROM', 'ACTIVE_TO'])->Fetch();
if (!$a) { logLine('FAIL акция skidka-na-vse не найдена'); exit(1); }

$orig = [
    'ACTIVE'      => $a['ACTIVE'],
    'ACTIVE_FROM' => (string)$a['ACTIVE_FROM'],
    'ACTIVE_TO'   => (string)$a['ACTIVE_TO'],
];
$aId = (int)$a['ID'];
$aName = (string)$a['NAME'];
$tmpId = 0;
logLine('INFO акция A: ID=' . $aId . ', исходно ACTIVE=' . $orig['ACTIVE'] . ', с ' . $orig['ACTIVE_FROM'] . ' по ' . $orig['ACTIVE_TO']);

$set = function (array $fields) use ($el, $aId): bool {
    $ok = $el->Update($aId, $fields);
    clearCache();
    return (bool)$ok;
};

try {
    $now = time();
    $today = mktime(23, 59, 0, (int)date('n', $now), (int)date('j', $now), (int)date('Y', $now));

    if (!$expiry) {
        // исходное состояние — активна, видна
        clearCache();
        check('активная акция видна', visible($domain, $aName));

        // ACTIVE=N
        $set(['ACTIVE' => 'N']);
        check('ACTIVE=N скрыта', !visible($domain, $aName));
        $set(['ACTIVE' => 'Y']);

        // ACTIVE_TO = вчера
        $set(['ACTIVE_TO' => fmt($now - 86400)]);
        check('ACTIVE_TO вчера скрыта', !visible($domain, $aName));
        $set(['ACTIVE_TO' => $orig['ACTIVE_TO']]);

        // ACTIVE_FROM = завтра
        $set(['ACTIVE_FROM' => fmt($now + 86400)]);
        check('ACTIVE_FROM завтра скрыта', !visible($domain, $aName));
        $set(['ACTIVE_FROM' => $orig['ACTIVE_FROM']]);

        // ACTIVE_TO пусто
        $set(['ACTIVE_TO' => '']);
        [$code, $body] = fetchShowcase($domain);
        check('ACTIVE_TO пусто: видна без ошибок',
            $code === 200 && strpos($body, htmlspecialchars($aName, ENT_QUOTES)) !== false && pageClean($body),
            'HTTP ' . $code);
        $set(['ACTIVE_TO' => $orig['ACTIVE_TO']]);

        // ACTIVE_TO = сегодня 23:59
        $set(['ACTIVE_TO' => fmt($today)]);
        check('ACTIVE_TO сегодня 23:59 видна', visible($domain, $aName));
        $set(['ACTIVE_TO' => $orig['ACTIVE_TO']]);

        // временная акция без картинки, скидки и дат
        $tmpName = 'TMP-' . substr(md5((string)$now), 0, 6);
        $tmpId = (int)$el->Add([
            'IBLOCK_ID' => $iblockId,
            'NAME'      => $tmpName,
            'CODE'      => 'tmp-rf2-' . substr(md5((string)$now), 0, 6),
            'ACTIVE'    => 'Y',
        ]);
        clearCache();
        if ($tmpId <= 0) {
            check('временный элемент создан', false, (string)$el->LAST_ERROR);
        } else {
            [$code, $body] = fetchShowcase($domain);
            $pos = strpos($body, htmlspecialchars($tmpName, ENT_QUOTES));
            if ($code !== 200 || $pos === false) {
                check('карточка без картинки/скидки', false, 'карточка не найдена, HTTP ' . $code);
            } else {
                $start = strrpos(substr($body, 0, $pos), 'class="action-card');
                $next = strpos($body, 'class="action-card', $pos);
                $seg = substr($body, (int)$start, $next === false ? null : $next - (int)$start);
                $empty = preg_match('/action-card__(image|discount|period)/', $seg) // блоков быть не должно
                    || preg_match('/<img/i', $seg);
                check('карточка без картинки/скидки: пустых блоков нет', !$empty && pageClean($body));
            }
        }
    } else {
        // --expiry: ACTIVE_TO = сейчас + 1 минута, затем ждём CACHE_TIME + 1 минуту
        $cacheTime = 600;
        $set(['ACTIVE_TO' => fmt($now + 60)]);
        check('EXPIRY акция видна до окончания', visible($domain, $aName));
        $wait = $cacheTime + 60;
        logLine('INFO ждём ' . $wait . ' с без ручного сброса кеша');
        sleep($wait);
        check('EXPIRY акция исчезла по истечении ACTIVE_TO и кеша', !visible($domain, $aName));
    }
} finally {
    $el->Update($aId, $orig);
    if ($tmpId > 0) {
        CIBlockElement::Delete($tmpId);
    }
    clearCache();
    $chk = CIBlockElement::GetList([], ['ID' => $aId], false, false, ['ACTIVE', 'ACTIVE_FROM', 'ACTIVE_TO'])->Fetch();
    $same = $chk && $chk['ACTIVE'] === $orig['ACTIVE']
        && (string)$chk['ACTIVE_FROM'] === $orig['ACTIVE_FROM']
        && (string)$chk['ACTIVE_TO'] === $orig['ACTIVE_TO'];
    logLine(($same ? 'OK' : 'FAIL') . ' восстановление акции A' . ($tmpId > 0 ? ', временный элемент удалён' : ''));
    if (!$same) { $fails++; }
}

logLine($fails === 0 ? 'ИТОГ: всё OK' : 'ИТОГ: FAIL x' . $fails);
exit($fails === 0 ? 0 : 1);
