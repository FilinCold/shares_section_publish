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
	/** Дата окончания в формате компонента (DISPLAY_ACTIVE_TO нет в штатном news.list — берём из FIELDS). */
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
?>
<div class="action-list">
<?php foreach ($arResult['ITEMS'] as $arItem):
	$this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem['IBLOCK_ID'], 'ELEMENT_EDIT'));
	$this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem['IBLOCK_ID'], 'ELEMENT_DELETE'), array('CONFIRM' => 'Удалить акцию?'));

	$pic = (is_array($arItem['PREVIEW_PICTURE']) && !empty($arItem['PREVIEW_PICTURE']['SRC'])) ? $arItem['PREVIEW_PICTURE'] : null;

	$period = actionsPeriodText(
		$arItem['DISPLAY_ACTIVE_FROM'] ?? '',
		actionsDisplayTo($arItem, $arParams['ACTIVE_DATE_FORMAT'] ?? '')
	);

	$discount = '';
	if (isset($arItem['PROPERTIES']['DISCOUNT']['VALUE']))
	{
		$v = $arItem['PROPERTIES']['DISCOUNT']['VALUE'];
		$discount = trim(is_array($v) ? implode(', ', $v) : (string)$v);
	}
	// ~PREVIEW_TEXT — исходное значение; в PREVIEW_TEXT спецсимволы уже закодированы (иначе двойное экранирование)
	$previewText = trim(strip_tags((string)($arItem['~PREVIEW_TEXT'] ?? $arItem['PREVIEW_TEXT'])));
?>
	<div class="action-card" id="<?=$this->GetEditAreaId($arItem['ID'])?>">
<?php if ($pic): ?>
		<div class="action-card__image">
			<a href="<?=htmlspecialcharsbx($arItem['DETAIL_PAGE_URL'])?>"><img src="<?=htmlspecialcharsbx($pic['SRC'])?>" alt="<?=htmlspecialcharsbx($arItem['NAME'])?>" loading="lazy"></a>
		</div>
<?php endif; ?>
		<h3 class="action-card__title"><?=htmlspecialcharsbx($arItem['NAME'])?></h3>
<?php if ($discount !== ''): ?>
		<div class="action-card__discount"><span class="action-card__label">Скидка / предложение:</span> <?=htmlspecialcharsbx($discount)?></div>
<?php endif; ?>
<?php if ($period !== ''): ?>
		<div class="action-card__period"><?=htmlspecialcharsbx($period)?></div>
<?php endif; ?>
<?php if ($previewText !== ''): ?>
		<div class="action-card__text"><?=htmlspecialcharsbx($previewText)?></div>
<?php endif; ?>
		<div class="action-card__more"><a href="<?=htmlspecialcharsbx($arItem['DETAIL_PAGE_URL'])?>">Подробнее</a></div>
	</div>
<?php endforeach; ?>
</div>
<?php if ($arParams['DISPLAY_BOTTOM_PAGER'] && !empty($arResult['NAV_STRING'])): ?>
	<?=$arResult['NAV_STRING']?>
<?php endif; ?>
