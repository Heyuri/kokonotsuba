<?php

/**
 * IPIOCondition
 */
interface IPIOCondition {
	/**
	 * Check whether the condition check should be performed.
	 *
	 * @param  string $type  Current mode ("predict" for preview warning, "delete" for actual deletion)
	 * @param  mixed  $limit Condition limit parameter
	 * @return boolean       Whether further checks are needed
	 */
	public static function check($board, $type, $limit);

	/**
	 * List post numbers that need to be deleted.
	 *
	 * @param  string $type  Current mode ("predict" for preview warning, "delete" for actual deletion)
	 * @param  mixed  $limit Condition limit parameter
	 * @return array         Array of post numbers to delete
	 */
	public static function listee($board, $type, $limit);

	/**
	 * Output Condition object information.
	 *
	 * @param  mixed  $limit Condition limit parameter
	 * @return string        Information string about this object
	 */
	public static function info($board, $limit);
}
