<?php
namespace ExternalModules;

class AbstractExternalModule
{
	private static $NON_HOOK_PERMISSIONS = array(
		'select_data',
		'update_data',
		'delete_data',
		'update_user_permissions'
	);

	function selectData($some, $params)
	{
		throw new Exception('Not yet implemented!');
	}

	function updateData($some, $params)
	{
		throw new Exception('Not yet implemented!');
	}

	function deleteData($some, $params)
	{
		throw new Exception('Not yet implemented!');
	}

	function updateUserPermissions($some, $params)
	{
		throw new Exception('Not yet implemented!');
	}

	function hook_redcap_add_edit_records_page($project_id, $instrument, $event_id)
	{
		// Do nothing.  This method is intended to be overridden by children.
	}

	function hook_redcap_control_center()
	{
		// Do nothing.  This method is intended to be overridden by children.
	}

	function hook_redcap_custom_verify_username($username)
	{
		// Do nothing.  This method is intended to be overridden by children.
	}

	function hook_redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id)
	{
		// Do nothing.  This method is intended to be overridden by children.
	}

	function hook_redcap_data_entry_form_top($project_id, $record, $instrument, $event_id, $group_id)
	{
		// Do nothing.  This method is intended to be overridden by children.
	}

	function hook_redcap_every_page_before_render($project_id)
	{
		// Do nothing.  This method is intended to be overridden by children.
	}

	function hook_redcap_every_page_top($project_id)
	{
		// Do nothing.  This method is intended to be overridden by children.
	}

	function hook_redcap_project_home_page($project_id)
	{
		// Do nothing.  This method is intended to be overridden by children.
	}

	function hook_redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id)
	{
		// Do nothing.  This method is intended to be overridden by children.
	}

	function hook_redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id)
	{
		// Do nothing.  This method is intended to be overridden by children.
	}

	function hook_redcap_survey_page($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id)
	{
		// Do nothing.  This method is intended to be overridden by children.
	}

	function hook_redcap_survey_page_top($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id)
	{
		// Do nothing.  This method is intended to be overridden by children.
	}

	function hook_redcap_user_rights($project_id)
	{
		// Do nothing.  This method is intended to be overridden by children.
	}

	# TODO - Add the rest of the hook methods.

	function __call($methodName, $arguments)
	{
		$permissionName = $this->methodNameToPermissionName($methodName);
		if ($this->isPermissionRequired($permissionName) && !$this->hasPermission($permissionName)) {
			throw new Exception("This module must request the \"$permissionName\" permission in order to call the $methodName() method.");
		}
	}

	private function isPermissionRequired($permissionName)
	{
		return strpos($permissionName, 'hook_') === 0 ||
			   in_array('$permissionName', self::$NON_HOOK_PERMISSIONS);
	}
}