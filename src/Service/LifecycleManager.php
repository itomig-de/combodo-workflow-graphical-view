<?php

/**
 * Copyright (C) 2013-2020 Combodo SARL
 *
 * This file is part of iTop.
 *
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 */

namespace Combodo\iTop\Extension\LifecycleSneakPeek\Service;

use Combodo\iTop\Extension\LifecycleSneakPeek\Helper\ConfigHelper;
use ContextTag;
use DBObject;
use Dict;
use MetaModel;
use utils;

class LifecycleManager
{
	/** @var \DBObject */
	private $oObject;

	/**
	 * Return if $oObject is eligible to the service
	 *
	 * @param \DBObject $oObject
	 *
	 * @return bool
	 * @throws \CoreException
	 */
	public static function IsEligibleObject(DBObject $oObject)
	{
		$sClass = get_class($oObject);

		// Check if among disabled classes
		$aDisabledClasses = ConfigHelper::GetModuleSetting('disabled_classes');
		if (is_array($aDisabledClasses) && in_array($sClass, $aDisabledClasses))
		{
			return false;
		}

		// Check if has state attribute
		$sStateAttCode = MetaModel::GetStateAttributeCode($sClass);
		if (empty($sStateAttCode))
		{
			return false;
		}

		return true;
	}

	/**
	 * Return an array of eligible classes and their state attribute code
	 *
	 * @return array
	 * @throws \CoreException
	 */
	public static function EnumEligibleClasses()
	{
		$aEligibleClasses = array();
		foreach(MetaModel::EnumRootClasses() as $sRootClass)
		{
			$sStateAttCode = MetaModel::GetStateAttributeCode($sRootClass);
			if(!empty($sStateAttCode))
			{
				$aEligibleClasses[$sRootClass] = array('state_att_code' => $sStateAttCode);
			}

			foreach(MetaModel::EnumChildClasses($sRootClass) as $sChildClass)
			{
				$sStateAttCode = MetaModel::GetStateAttributeCode($sChildClass);
				if(!empty($sStateAttCode))
				{
					$aEligibleClasses[$sChildClass] = array('state_att_code' => $sStateAttCode);
				}
			}
		}

		return $aEligibleClasses;
	}

	/**
	 * Return an array of the required CSS files URLs
	 *
	 * @return array
	 * @throws \Exception
	 */
	public static function GetCSSFilesUrls()
	{
		$sDefaultCSSRelPath = utils::GetCSSFromSASS('env-'.utils::GetCurrentEnvironment().'/'.ConfigHelper::GetModuleCode().'/asset/css/default.scss');

		return array(
			utils::GetAbsoluteUrlAppRoot().$sDefaultCSSRelPath,
		);
	}

	/**
	 * Return an array of the required JS files URLs
	 *
	 * @return array
	 * @throws \Exception
	 */
	public static function GetJSFilesUrls()
	{
		$sBaseUrl = utils::GetAbsoluteUrlModulesRoot().ConfigHelper::GetModuleCode().'/asset/js/';

		return array(
			$sBaseUrl.'lifecycle_sneakpeek.js',
			$sBaseUrl.static::GetJSWidgetNameForUI().'.js',
		);
	}

	/**
	 * Return the name of the JS widget for the UI
	 *
	 * @return string
	 */
	public static function GetJSWidgetNameForUI()
	{
		return ContextTag::Check(ContextTag::TAG_PORTAL) ? 'lifecycle_sneakpeek_portal' : 'lifecycle_sneakpeek_backoffice';
	}

	/**
	 * Return the endpoint absolute URL for AJAX calls
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function GetEndpoint()
	{
		return utils::GetAbsoluteUrlModulePage(ConfigHelper::GetModuleCode(), 'ajax-operations.php');
	}

	/**
	 * Return an array of the CSS classes for the "show button"
	 *
	 * @return array
	 */
	public static function GetShowButtonCSSClasses()
	{
		$aCSSClasses = array();

		$sModuleParameter = ConfigHelper::GetModuleSetting('show_button_css_classes');
		foreach(explode(' ', $sModuleParameter) as $sCSSClass)
		{
			$aCSSClasses[] = trim($sCSSClass);
		}

		return $aCSSClasses;
	}

	/**
	 * LifecycleManager constructor.
	 *
	 * @param \DBObject $oObject
	 */
	public function __construct(DBObject $oObject)
	{
		$this->oObject = $oObject;
	}

	/**
	 * Return the JS snippet to instantiate the lifecycle widget for $oObject
	 *
	 * @return string
	 * @throws \CoreException
	 * @throws \Exception
	 */
	public function GetJSWidgetSnippetForObjectDetails()
	{
		$sObjClass = get_class($this->oObject);
		$sObjID = $this->oObject->GetKey();
		$sObjStateAttCode = MetaModel::GetStateAttributeCode($sObjClass);
		$sObjState = $this->oObject->GetState();

		$sWidgetName = $this->GetJSWidgetNameForUI();
		$sShowButtonCSSClassesAsJSON = json_encode(static::GetShowButtonCSSClasses());
		$sEndpoint = static::GetEndpoint();

		$sDictEntryShowButtonTooltipAsJSON = Dict::S('lifecycle-sneakpeek:UI:Button:ShowLifecycle');
		$sDictEntryModalTitleAsJSON = Dict::S('lifecycle-sneakpeek:UI:Modal:Title');
		$sDictEntryModalCloseButtonLabelAsJSON = Dict::S('UI:Button:Close');

		return <<<JS
\$('.object-details[data-object-class="{$sObjClass}"][data-object-id="{$sObjID}"] *[data-attribute-code="{$sObjStateAttCode}"][data-attribute-flag-read-only="true"]').{$sWidgetName}({
	object_class: '{$sObjClass}',
	object_id: '{$sObjID}',
	object_state: '{$sObjState}',
	show_button_css_classes: {$sShowButtonCSSClassesAsJSON},
	endpoint: '{$sEndpoint}',
	dict: {
		show_button_tooltip: '{$sDictEntryShowButtonTooltipAsJSON}',
		modal_title: '{$sDictEntryModalTitleAsJSON}',
		modal_close_button_label: '{$sDictEntryModalCloseButtonLabelAsJSON}'
	}
});
JS;
	}

	/**
	 * Return the path of the lifecycle image
	 *
	 * @param array $aStimuliToHide
	 * @param bool  $bHideInternalStimuli
	 *
	 * @return string
	 * @throws \CoreException
	 * @throws \ReflectionException
	 */
	public function GetLifecycleImage($aStimuliToHide = array(), $bHideInternalStimuli = ConfigHelper::DEFAULT_SETTING_HIDE_INTERNAL_STIMULI)
	{
		return GraphvizGenerator::GenerateObjectLifecycleAsImage($this->oObject, $aStimuliToHide, $bHideInternalStimuli);
	}
}