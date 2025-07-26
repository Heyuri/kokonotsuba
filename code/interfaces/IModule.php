<?php

/**
 * IModule
 */
interface IModule {
	/**
	 * Method to return the module name
	 *
	 * @return string Module name. Suggested return format: mod_xxx : short description
	 */
	public function getModuleName();

	/**
	 * Method to return the module version number
	 *
	 * @return string Module version number
	 */
	public function getVersion(): string ;
}
