<?php namespace Instafilter;

/**
 * Filter class
 *
 * This holds all of the common things between filters like getting access to the imagemagick instance etc.
 *
 * @package    Instafilter
 */
abstract class Filter
{
	private $imagick = null;

	
	public function __construct(array $configuration = array())
	{
		$this->configuration = array_merge(array(
				'imagemagick_dir' => '/usr/bin/',
			), $configuration);
		return $this;
	}

	/** You should only call this when you're ready to save, it often overwrites your image a few times (not always though) **/
	abstract public function apply_filter();

	/**
	 *  This is the imagemagick instance to perform the filters on
	 * 
	 * It's recommended you supply a temporary file here!
	 * Some functions, such as brightness_contrast, overwrite the image.
	 * 
	 * @todo decouple from Image class
	 * 
	 */ 
	public function imagick(&$imagick = null)
	{
		if ($imagick === null)
		{

			return $this->image()->imagick();
		}
		$this->imagick = $imagick;
		return $this;
	}

	public function image(&$image = null)
	{
		if ($image === null)
		{

			return $this->image;
		}
		$this->image = $image;
		return $this;
	}

	/**
	 * Replicate Colorize function
	 * @param string $color a hex or rgb(a) color
	 * @param int $composition use imagicks constants here
	 * @return Filter
	 */
	public function colorize($color, $composition = \Imagick::COMPOSITE_MULTIPLY)
	{	
		$overlay = new \Imagick();
		$overlay->newPseudoImage($this->imagick()->getImageWidth(), $this->imagick()->getImageHeight(),"canvas:$color");
		$this->imagick()->compositeImage($overlay, $composition, 0, 0);
		return $this;
		//$this->exec('convert',"\( -clone 0 -fill '$color' -colorize 100% \) -compose {$composition} -composite ");
	}

	/**
	 * Change the gamma of an image
	 * @param float $gamma normally between 0.8 and 2
	 * @param int $channel Use the Imagick constants for this
	 * @return Filter
	 */
	public function gamma($gamma, $channel = null)
	{	
		$this->imagick()->gammaImage($gamma);
		return $this;
	}

	/**
	 * Replicate Photoshop's levels function.
	 * 
	 * @param float $gamma 
	 * @param int $input_min between 0 and 255, same as photoshops
	 * @param int $input_max between 0 and 255, same as photoshops
	 * @param int $output_min between 0 and 255, same as photoshops
	 * @param int $output_max between 0 and 255, same as photoshops
	 * @param int $channel use imagemagicks constants
	 * @return Filter
	 */
	public function levels($gamma = 1, $input_min = 0, $input_max = 255, $output_min = 0, $output_max = 255, $channel = \Imagick::CHANNEL_ALL)
	{
		$range = $this->imagick()->getQuantumRange();
		$range = $range['quantumRangeLong'];

		//convert photoshop's units to imagemagicks
		$input_min = round(($input_min / 255) * $range);
		$input_max = round(($input_max / 255) * $range);
		$output_min = round(($output_min / 255) * $range);
		$output_max = round(($output_max / 255) * $range);

		// set input levels
		$this->imagick()->levelImage($input_min, $gamma, $input_max, $channel);

		// set output levels
   		$this->imagick()->levelImage(-$output_min, 1.0, $range + ($range - $output_max), $channel);

   		return $this;
	}

	/**
	 * Replicate brightness/contrast photoshop function
	 * 
	 * Now this one is a bit of a pain. PHP's extension doesn't provide us with this handle (yet?)
	 * So we have to save the image to disk at this point, perform the function using the command line, and reload the image. yay.
	 * 
	 * @param int $brightness this is should be -150 <= brightnes <= 150. 0 for no change.
	 * @param int $contrast this is should be -150 <= contrast <= 150. 0 for no change.
	 * @return Filter
	 */
	public function brightness_contrast($brightness, $contrast)
	{
		//normalise from photoshop's units to imagicks -- this is a guestimate
		$brightness_normalised = (abs($brightness)/150)*5;
		$contrast_normalised = (abs($contrast)/150)*5;

		if ($contrast_normalised == 0 and $brightness_normalised == 0)
		{
			return $this;
		}
		
		$overlay = new \Imagick();
		$overlay->newPseudoImage(1, 1000,"gradient:");
		$overlay->rotateImage('#fff', 90);

		if ($contrast_normalised != 0)
		{
			$overlay->sigmoidalContrastImage ($contrast > 0,  $contrast_normalised,  50 );
		}

		if ($brightness_normalised != 0)
		{
			$overlay->sigmoidalContrastImage ($brightness > 0,  $brightness_normalised,  0 );
		}
		
		$this->imagick()->clutImage($overlay);

		return $this;
	}

	/**
	 * Execute a manual CLI command -- normally used when there's no PHP alternatice
	 * 
	 * @param string $command normally 'convert'
	 * @param string $params the CLI params
	 * @return Filter
	 */
	public function exec($command, $params)
	{
		$input = $this->imagick()->getImageFilename();
		$params = " \"{$input}\" " .$params. " \"{$input}\"";
		$this->_exec($command, $params);

		return $this;
	}

	/**
	 * Execute the command
	 * 
	 * This is duplicated from the image class so that this can be used without the in-built image class
	 *
	 * Based on FuelPHP's command
	 * 
	 * @param type $program 
	 * @param type $params 
	 * @param type $passthru 
	 * @return type
	 */
	private function _exec($program, $params, $passthru = false)
	{
		//  Determine the path
		$this->im_path = realpath($this->configuration['imagemagick_dir'].$program);

		// store the filename for this image
		$filename = $this->imagick()->getImageFilename();

		// check imagemagick is where we expect it
		if ( ! $this->im_path)
		{
			$this->im_path = realpath($this->configuration['imagemagick_dir'].$program.'.exe');
		}
		if ( ! $this->im_path)
		{
			throw new \RuntimeException("imagemagick executables not found in ".$this->configuration['imagemagick_dir']);
		}

		// save the filters that have already been applied
		$this->imagick()->writeImage($filename);


		$command = $this->im_path." ".$params;

		$code = 0;
		$output = null;


		//do the actual command
		$passthru ? passthru($command) : exec($command, $output, $code);
		
		// reload the imagemagick instance once the manual command is done
		$this->image->imagick(new \Imagick($filename));

		// check if it's successful
		if ($code != 0)
		{
			throw new \Exception("imagemagick failed to edit the image. Returned with $code.<br /><br />Command:\n <code>$command</code>");
		}

		return $output;
	}

	/**
	 * Replicate HSL function
	 * 
	 * Imagemagick calls this 'modulate
	 * '
	 * @param int $hue -100 <= hue <= 100. 0 is no change.
	 * @param int $saturation -100 <= hue <= 100. 0 is no change.
	 * @param int $lightness -100 <= hue <= 100. 0 is no change.
	 * @return Filter
	 */
	public function hsl($hue = 0, $saturation = 0, $lightness = 0)
	{
		$hue += 100;
		$saturation += 100;
		$lightness += 100;

		$this->imagick()->modulateImage($lightness, $saturation, $hue);
		return $this;
	}

	/**
	 * Replicate photoshop's curves function
	 * 
	 * This takes a series of points and generates a function for the graph that fits the points.
	 * That function is then applied to each pixes, as in photoshop.
	 * It should use curves_graph once the function has been generated.
	 * 
	 * @param array $points an array of arrays. array(array(12, 32), array(32,56)) etc.
	 * @param int $channel use imagemagicks contants here
	 * @return Filter
	 */
	public function curves($points, $channel = null)
	{
		//polynomial interpolation
		throw new \Exception('Curves is not yet implemented.');

		return $this;
	}

	/**
	 * Perform an imagemagick-style function on each pixel
	 * @param string $fx the function
	 * @return Filter
	 */
	public function curves_graph($fx)
	{
		$this->imagick()->fxImage($fx);

		return $this;
	}

	
	/**
	 * Adds a vignette to the image
	 * @param string $color the colour of the vignette
	 * @param int $composition an imagick constant defining the composition to use
	 * @param float $crop_factor defines the strenth of the vignette
	 * @return Filter
	 */
	public function vignette($color, $composition = \Imagick::COMPOSITE_DEFAULT, $crop_factor = 1.5)
	{	
		$height = $this->imagick()->getImageHeight();
		$width = $this->imagick()->getImageWidth();

		$crop_x = floor($height * $crop_factor);
		$crop_y = floor($width * $crop_factor);

		$overlay = new \Imagick();
		$overlay->newPseudoImage($crop_x, $crop_y,"radial-gradient:rgba(0,0,0,0)-$color");
		$this->imagick()->compositeImage($overlay, $composition, ($width - $crop_x) / 2, ($height - $crop_y)/2);

		return $this;
	}
}