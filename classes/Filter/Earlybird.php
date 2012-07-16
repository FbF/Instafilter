<?php Namespace Instafilter\Filter;

/**
 * Earlybird class
 *
 * A sort-of sepia filter
 *
 * @package    Instafilter
 */

use Instafilter;

class Earlybird extends Instafilter\Filter
{
	public function apply_filter() {
		$this->hsl(0, -32, 1)
			->gamma(1.19)
			->levels(1, 0, 255, 27, 255, \Imagick::CHANNEL_RED)
			->brightness_contrast(15, 36)
			->hsl(0, -17, 0)
			->levels(0.92, 0, 235)

			->colorize('rgb(251,243,220)')
			->vignette('rgb(184,184,184)', \Imagick::COMPOSITE_COLORBURN)
			->vignette('rgb(251,243,220)', \Imagick::COMPOSITE_MULTIPLY);
	}
}