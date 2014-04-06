<?php

class T {
	static public $stack = array();

	static public function __callStatic($name, $arguments) {
		echo "<$name";
		$count = count($arguments);
		if ($count & 1) $push = array_pop($arguments);
		else $push = NULL;
		foreach ($arguments as $index => $argument) {
			if ($index & 1) echo "\"$argument\"";
			else echo " $argument=";
		}
		if (is_null($push)) echo " />";
		else {
			echo ">";
			if (is_string($push)) echo "$push</$name>";
			elseif ($push) self::$stack[] = $name;
			else echo "</$name>";
		}
	}

	public function __call($name, $arguments) {
		self::__callStatic($name, $arguments);
	}

	static public function _() {
		if (self::$stack) {
			echo '</' . array_pop(self::$stack) . ">";
			return count(self::$stack);
		} else return FALSE;
	}

	static public function __() {
		while (self::_());
	}
}

/* example
T::h1('Hello');
T::img('src', 'images/fullscreen.png');
T::form('action', 'form.php', TRUE);
	T::a('href', 'images/fullscreen.png', FALSE);
T::_();
*/

?>
