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

	function hook_redcap_project_home_page($project_id)
	{
		// Do nothing.  This method is intended to be overridden by children.
	}

	function hook_redcap_add_edit_records_page($project_id, $instrument, $event_id)
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