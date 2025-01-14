<?php
/*
PIO - Pixmicat! data source I/O
*/

// 協助設定 status 旗標的類別
class FlagHelper{
	var $_status;

	function __construct($status=''){
		$this->_write($status);
	}

	function _write($status=''){
		$this->_status = $status;
	}

	function toString(){
		return $this->_status;
	}

	function get($flag){
		$result = preg_match('/_('.$flag.'(\:(.*))*)_/U', $this->toString(), $match);
		return $result ? $match[1] : false;
	}

	function exists($flag){
		return $this->get($flag) !== false;
	}

	function value($flag){
		$wholeflag = $this->get($flag);
		if($scount = substr_count($wholeflag, ':')){
			$wholeflag = preg_replace('/^'.$flag.'\:/', '', $wholeflag);
			return ($scount > 1 ? explode(':', $wholeflag) : $wholeflag);
		}else return $wholeflag !== false;
	}

	function add($flag, $value=null){
		return $this->update($flag, $value);
	}

	function update($flag, $value=null){
		if($value===null){
			$ifexist = $this->get($flag);
			if($ifexist !== $flag) $this->_write($this->toString()."_{$flag}_");
		}else{
			if(is_array($value)) $value = $this->join($value); // Array Flatten
			$ifexist = $this->get($flag);
			if($ifexist !== $flag.':'.$value){
				if($ifexist) $this->_write($this->replace($ifexist, "$flag:$value")); // 已立flag，不同值
				else $this->_write($this->toString()."_$flag:{$value}_"); // 無flag
			}
		}
		return $this;
	}

	function replace($from, $to){
		return str_replace("_{$from}_", "_{$to}_", $this->toString());
	}

	function remove($flag){
		$wholeflag = $this->get($flag);
		$this->_write(str_replace("_{$wholeflag}_", '', $this->toString()));
		return $this;
	}

	function toggle($flag){
		return ($this->get($flag) ? $this->remove($flag) : $this->add($flag));
	}

	function offsetValue($flag, $d=0){
		$v = intval($this->value($flag));
		return $this->update($flag, $v + $d);
	}

	function plus($flag){ return $this->offsetValue($flag, 1); }
	function minus($flag){ return $this->offsetValue($flag, -1); }

	function join(){
		$arg = func_get_args();
		$newval = array();
		foreach($arg as $a){
			if(is_array($a)) array_push($newval, implode(':', $a));
			else array_push($newval, $a);
		}
		return implode(':', $newval);
	}

	public function __toString() {
		return sprintf('%s {status = %s}', __CLASS__, $this->toString());
	}
}

// Automatic Article Deletion Mechanism
class PIOSensor {
	
	/**
	 * Check if any condition requires processing.
	 *
	 * @param string $type The type of check to perform.
	 * @param array $conditions Array of objects and their corresponding parameters.
	 * @return bool True if any condition passes the check, otherwise false.
	 */
	public static function check($board, $type, array $conditions) {
		foreach ($conditions as $object => $params) {
			// Call the 'check' method on the object/class with given parameters
			if (call_user_func_array([$object, 'check'], [$board, $type, $params]) === true) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get a sorted, unique list of results from conditions.
	 *
	 * @param string $type The type of operation to list.
	 * @param array $conditions Array of objects and their corresponding parameters.
	 * @return array Sorted and unique list of results.
	 */
	public static function listee($board, $type, array $conditions) {
		$resultList = []; // Array to collect results

		foreach ($conditions as $object => $params) {
			// Merge results from calling 'listee' on the object/class
			$resultList = array_merge(
				$resultList,
				call_user_func_array([$object, 'listee'], [$board, $type, $params])
			);
		}

		// Sort results in ascending order and remove duplicates
		sort($resultList);
		return array_unique($resultList);
	}

	/**
	 * Gather information from all conditions.
	 *
	 * @param array $conditions Array of objects and their corresponding parameters.
	 * @return string Concatenated information string.
	 */
	public static function info($board, array $conditions) {
		$sensorInfo = ''; // String to collect information

		foreach ($conditions as $object => $params) {
			// Append information from calling 'info' on the object/class
			$sensorInfo .= call_user_func_array([$object, 'info'], [$board, $params]) . "\n";
		}

		return $sensorInfo;
	}
}


