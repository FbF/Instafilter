<?php Namespace Instafilter\Filter;

/**
 * Inkwell class
 *
 * A black and white filter
 *
 * @package    Instafilter
 */

use Instafilter;

class Inkwell extends Instafilter\Filter
{
	public function apply_filter() {
		$this->hsl(0, -100, 0)
			->curves_graph('-0.062*u^3-0.104*u^2+1.601*u-0.175')
			->brightness_contrast(-10, 48);
			
		// it would be nice to do the curves like this!
		/*$this->curves(array(
			array(11,0),
			array(69,86),
			array(158,185),
			array(255,220),
		));*/		
	}
}
