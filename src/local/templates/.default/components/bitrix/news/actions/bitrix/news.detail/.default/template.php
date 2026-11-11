<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)
{
	die();
}
/** @var array $arParams */
/** @var array $arResult */

if (!function_exists('actionsPeriodText'))
{
	/**
	 * Период действия: обе даты — «с … по …», только начало — «с …»,
	 * только конец — «до …», нет дат — пустая строка.
	 */
	function actionsPeriodText($from, $to)
	{
		$from = trim((string)$from);
		$to = trim((string)$to);
		if ($from !== '' && $to !== '')
		{
			return 'с ' . $from . ' по ' . $to;
		}
		if ($from !== '')
		{
			return 'с ' . $from;
		}
		if ($to !== '')
		{
			return 'до ' . $to;
		}
		return '';
	}
}

if (!function_exists('actionsDisplayTo'))
{
	/** Дата окончания в формате компонента (DISPLAY_ACTIVE_TO нет в штатном news.detail — берём из FIELDS). */
	function actionsDisplayTo(array $item, $format)
	{
		if (!empty($item['DISPLAY_ACTIVE_TO']))
		{
			return $item['DISPLAY_ACTIVE_TO'];
		}
		$raw = $item['FIELDS']['ACTIVE_TO'] ?? ($item['ACTIVE_TO'] ?? '');
		if ($raw === '' || $raw === null)
		{
			return '';
		}
		$ts = MakeTimeStamp($raw);
		if (!$ts)
		{
			return '';
		}
		return CIBlockFormatProperties::DateFormat($format ?: 'd.m.Y', $ts);
	}
}

// Картинка: детальная, при её отсутствии — анонсная; нет обеих — блок не выводится.
$pic = null;
foreach (array('DETAIL_PICTURE', 'PREVIEW_PICTURE') as $code)
{
	if (is_array($arResult[$code]) && !empty($arResult[$code]['SRC']))
	{
		$pic = $arResult[$code];
		break;
	}
}

$period = actionsPeriodText(
	$arResult['DISPLAY_ACTIVE_FROM'] ?? '',
	actionsDisplayTo($arResult, $arParams['ACTIVE_DATE_FORMAT'] ?? '')
);

$discount = '';
if (isset($arResult['PROPERTIES']['DISCOUNT']['VALUE']))
{
	$v = $arResult['PROPERTIES']['DISCOUNT']['VALUE'];
	$discount = trim(is_array($v) ? implode(', ', $v) : (string)$v);
}

// ~ПОЛЕ — исходное значение; в PREVIEW_TEXT/DETAIL_TEXT типа text спецсимволы уже закодированы (иначе двойное экранирование)
$previewText = trim(strip_tags((string)($arResult['~PREVIEW_TEXT'] ?? $arResult['PREVIEW_TEXT'])));
$detailText = trim((string)($arResult['~DETAIL_TEXT'] ?? $arResult['DETAIL_TEXT']));
$detailIsHtml = ($arResult['DETAIL_TEXT_TYPE'] ?? 'text') === 'html';
?>
<div class="action-detail">
<?php if ($pic): ?>
	<div class="action-detail__image">
		<img src="<?=htmlspecialcharsbx($pic['SRC'])?>" alt="<?=htmlspecialcharsbx($arResult['NAME'])?>">
	</div>
<?php endif; ?>
<?php if ($discount !== ''): ?>
	<div class="action-detail__discount"><span class="action-detail__label">Скидка / предложение:</span> <?=htmlspecialcharsbx($discount)?></div>
<?php endif; ?>
<?php if ($period !== ''): ?>
	<div class="action-detail__period"><?=htmlspecialcharsbx($period)?></div>
<?php endif; ?>
<?php if ($previewText !== ''): ?>
	<div class="action-detail__preview"><?=htmlspecialcharsbx($previewText)?></div>
<?php endif; ?>
<?php if ($detailText !== ''): ?>
	<div class="action-detail__text"><?= $detailIsHtml ? $detailText : nl2br(htmlspecialcharsbx($detailText)) ?></div>
<?php endif; ?>
</div>
