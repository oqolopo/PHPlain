<?php

class PHPlainControl {

	static public function pageNavigator(PHPlainQuery $query, $current, $size,
										 $param = '?page',
										 $range = 10, $snap = 5)
	{
		$low = (round($current / $snap) - 1) * $snap;
		if ($low < 0) $low = 0;
		$count = (int) ($query->count() / $size);
		$high = $low + $range;
		if ($high > $count) {
			$high = $count;
			if ($low >= ($high - $range)) $low = $high - $range;
		}
		if ($current > 1) {
			?><a href="<?php echo "$param=" . ($current - 1); ?>"><?php
		}
		?>&lt;<?php
		if ($current > 1) echo '</a>';
		?>&nbsp;<?php
		for (;$low++ < $high;) {
			if ($low != $current) {
				?><a href="<?php echo "$param=$low"; ?>"><?php
			}
			echo "$low";
			if ($low != $current) {
				?></a><?php
			}
			?>&nbsp;<?php
		}
		if ($current < $count) {
			?><a href="<?php echo "$param=" . ($current + 1); ?>"><?php
		}
		?>&gt;<?php
		if ($current < $count) echo '</a>';
	}

}

?>