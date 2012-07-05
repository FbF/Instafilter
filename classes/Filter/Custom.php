<?php Namespace Instafilter\Filter;

/**
 * Custom filter
 * 
 * Allows the user to quickly throw together a filter using closures
 * 
 * new Custom(function($filter){
 *		$filter->hsl(12,3,1);
 * });
 *
 * @package    Instafilter
 */

use Instafilter;

class Custom extends Instafilter\Filter
{
	private static $_closure = null;

	public function __construct($closure, $configuration)
	{
		parent::__construct($configuration);
		self::$_closure = $closure;
	}
	public function apply_filter() {
		if ($closure !== null)
		{
			self::$closure($this);
		}
	}
}
