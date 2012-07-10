<?php namespace Instafilter;

/**
 * Image class
 *
 * This image class allows for the basic manipulation of images and the addition
 * of any number of instagram-style filters
 * 
 * Based on the FuelPHP 1.x Image class http://fuelphp.com
 *
 * @package    Instafilter
 */
class Image
{

	public $image_temp = '';
	public $image = '';

	protected $imagick = null;
	protected $configuration = array();
	protected $queued_actions  = array();
	protected $accepted_extensions = array('png', 'gif', 'jpg', 'jpeg');
	protected $im_path = '';

	public function __construct(array $configuration = array())
	{
		$this->configuration = array_merge(array(
				'debug' => false,
				'imagemagick_dir' => '/usr/bin/',
				'temp_append' => 'image_',
				'temp_dir' => './tmp/',
				'clear_queue' => false,
				'quality' => 100,
			), $configuration);

		return $this;
	}
	
	public static function load($filename)
	{

		$filename = realpath($filename);
		
		// create new image
		$instance = new self();

		if (file_exists($filename))
		{
			// Check the extension
			$ext = $instance->check_extension($filename, false);
			if ($ext !== false)
			{
				$instance->image_fullpath = $filename;
				$instance->image_directory = dirname($filename);
				$instance->image_filename = basename($filename);
				$instance->image_extension = $ext;

				// here we create a temporary file that we apply our filters to
				if (empty($instance->image_temp))
				{
					do
					{

						$instance->image_temp = $instance->configuration['temp_dir'].substr($instance->configuration['temp_append'].md5(time() * microtime()), 0, 32).'.png';
					}
					while (file_exists($instance->image_temp));
				}
				elseif (file_exists($instance->image_temp))
				{
					$instance->debug('Removing previous temporary image.');
					unlink($instance->image_temp);
				}

				$instance->debug('Temp file: '.$instance->image_temp);
				if (!file_exists($instance->configuration['temp_dir']) || !is_dir($instance->configuration['temp_dir']))
				{
					throw new \RuntimeException("The temp directory that was given does not exist.");
				}
				elseif (!touch($instance->configuration['temp_dir'] . $instance->configuration['temp_append'] . '_touch'))
				{
					throw new \RuntimeException("Could not write in the temp directory.");
				}


				// Move the image to the temporary place
				$instance->imagick = new \Imagick($instance->image_fullpath);
				$instance->imagick->writeImage($instance->image_temp);


			}
			else
			{
				throw new \RuntimeException("The library does not support this filetype for $filename.");
			}
		}
		else
		{
			throw new \OutOfBoundsException("Image file $filename does not exist.");
		}
		return $instance;
	}

	public function imagick($set = null)
	{
		if ($set === null) return $this->imagick;

		$this->imagick = $set;
		return $this;
	}

	public function image()
	{
		return $this->image_temp;
	}


	public function save($output)
	{
		$this->run_queue();
		$this->imagick->writeImage($output);

		return $this;
	}

	public function apply_filter($filter)
	{
		$this->_queue('apply_filter', $filter);
		return $this;
	}

	public function _apply_filter($filter)
	{
		//give the image to the filter
		$filter->image($this);

		//apply the filter
		$filter->apply_filter();
		return $this;
	}

	public function sizes()
	{
		return (object) $this->imagick()->getImageGeometry();
	}

	/**
	 * Checks if the extension is accepted by this library, and if its valid sets the $this->image_extension variable.
	 *
	 * @param   string   $filename
	 * @param   boolean  $writevar  Decides if the extension should be written to $this->image_extension
	 * @return  boolean
	 */
	protected function check_extension($filename, $writevar = true)
	{
		$return = false;
		foreach ($this->accepted_extensions as $ext)
		{
			if (strtolower(substr($filename, strlen($ext) * -1)) == strtolower($ext))
			{
				$writevar and $this->image_extension = $ext;
				$return = $ext;
			}
		}
		return $return;
	}

	/**
	 * Converts percentages, negatives, and other values to absolute integers.
	 *
	 * @param   string   $input
	 * @param   boolean  $x  Determines if the number relates to the x-axis or y-axis.
	 * @return  integer  The converted number, usable with the image being edited.
	 */
	protected function convert_number($input, $x = null)
	{
		// Sanitize double negatives
		$input = str_replace('--', '', $input);

		$orig = $input;
		$sizes = $this->sizes();
		$size = $x ? $sizes->width : $sizes->height;
		// Convert percentages to absolutes
		if (substr($input, -1) == '%')
		{
			$input = floor((substr($input, 0, -1) / 100) * $size);
		}
		// Negatives are based off the bottom right
		if ($x !== null and $input < 0)
		{
			$input = $size + $input;
		}
		return $input;
	}

	public function resize($width, $height = null, $keepar = true, $pad = false)
	{
		$this->_queue('resize', $width, $height, $keepar, $pad);
		return $this;
	}


	protected function _resize($width, $height = null, $keepar = true, $pad = true)
	{
		$sizes = $this->sizes();
		if ($height == null or $width == null)
		{
			if ($height == null and substr($width, -1) == '%')
			{
				$height = $width;
			}
			elseif (substr($height, -1) == '%' and $width == null)
			{
				$width = $height;
			}
			else
			{
				
				if ($height == null and $width != null)
				{
					$height = $width * ($sizes->height / $sizes->width);
				}
				elseif ($height != null and $width == null)
				{
					$width = $height * ($sizes->width / $sizes->height);
				}
				else
				{
					throw new \InvalidArgumentException("Width and height cannot be null.");
				}
			}
		}

		$origwidth  = $this->convert_number($width, true);
		$origheight = $this->convert_number($height, false);
		$width      = $origwidth;
		$height     = $origheight;
		$x = 0;
		$y = 0;
		if ($keepar)
		{
			// See which is the biggest ratio
			if (function_exists('bcdiv'))
			{
				$width_ratio  = bcdiv((float) $width, $sizes->width, 10);
				$height_ratio = bcdiv((float) $height, $sizes->height, 10);
				$compare = bccomp($width_ratio, $height_ratio, 10);
				if ($compare > -1)
				{
					$height = ceil((float) bcmul($sizes->height, $height_ratio, 10));
					$width = ceil((float) bcmul($sizes->width, $height_ratio, 10));
				}
				else
				{
					$height = ceil((float) bcmul($sizes->height, $width_ratio, 10));
					$width = ceil((float) bcmul($sizes->width, $width_ratio, 10));
				}
			}
			else
			{
				$width_ratio  = $width / $sizes->width;
				$height_ratio = $height / $sizes->height;
				if ($width_ratio >= $height_ratio)
				{
					$height = ceil($sizes->height * $height_ratio);
					$width = ceil($sizes->width * $height_ratio);
				}
				else
				{
					$height = ceil($sizes->height * $width_ratio);
					$width = ceil($sizes->width * $width_ratio);
				}
			}
		}

		if ($pad)
		{
			$x = floor(($origwidth - $width) / 2);
			$y = floor(($origheight - $height) / 2);
		}
		else
		{
			$origwidth  = $width;
			$origheight = $height;
		}

		//it's more efficient to save it to the temp file, and do our changes there

		//$this->imagick()->setImageGravity(\Imagick::GRAVITY_CENTER);
		//$this->imagick()->resizeImage($width, $height);

		$image = '"'.$this->image().'"';
		$this->exec('convert', "-define png:size=".$origwidth."x".$origheight." ".$image." ".
			"-background none ".
			"-resize \"".($pad ? $width : $origwidth)."x".($pad ? $height : $origheight)."!\" ".
			"-gravity center ".
			"-extent ".$origwidth."x".$origheight." ".$image);

	}

	/**
	 * Queues a function to run at a later time.
	 *
	 * @param  string  $function  The name of the function to be ran, without the leading _
	 */
	protected function _queue($function)
	{
		$func = func_get_args();
		$tmpfunc = array();
		for ($i = 0; $i < count($func); $i++)
		{
			$tmpfunc[$i] = var_export($func[$i], true);
		}

		$this->debug("Queued <code>" . implode(", ", $tmpfunc) . "</code>");
		$this->queued_actions[] = $func;
	}

	/**
	 * Runs all queued actions on the loaded image.
	 *
	 * @param  boolean  $clear  Decides if the queue should be cleared once completed.
	 */
	public function run_queue($clear = null)
	{
		foreach ($this->queued_actions as $action)
		{
			$tmpfunc = array();
			for ($i = 0; $i < count($action); $i++)
			{
				$tmpfunc[$i] = var_export($action[$i], true);
			}
			$this->debug('', "<b>Executing <code>" . implode(", ", $tmpfunc) . "</code></b>");
			call_user_func_array(array(&$this, '_' . $action[0]), array_slice($action, 1));
		}
		if (($clear === null and $this->configuration['clear_queue']) or $clear === true)
		{
			$this->queued_actions = array();
		}
	}

	/**
	 * Executes the specified imagemagick executable and returns the output.
	 *
	 * @param   string   $program   The name of the executable.
	 * @param   string   $params    The parameters of the executable.
	 * @param   boolean  $passthru  Returns the output if false or pass it to browser.
	 * @return  mixed    Either returns the output or returns nothing.
	 */
	public function exec($program, $params, $passthru = false)
	{
		//  Determine the path
		$this->im_path = realpath($this->configuration['imagemagick_dir'].$program);

		if ( ! $this->im_path)
		{
			$this->im_path = realpath($this->configuration['imagemagick_dir'].$program.'.exe');
		}
		if ( ! $this->im_path)
		{
			throw new \RuntimeException("imagemagick executables not found in ".$this->configuration['imagemagick_dir']);
		}

		$this->imagick()->writeImage();

		$command = $this->im_path." ".$params;
		$this->debug("Running command: <code>$command</code>");
		$code = 0;
		$output = null;

		$passthru ? passthru($command) : exec($command, $output, $code);

		$this->imagick(new \Imagick($this->image()));

		if ($code != 0)
		{
			throw new \Exception("imagemagick failed to edit the image. Returned with $code.<br /><br />Command:\n <code>$command</code>");
		}

		return $output;
	}

	/**
	 * Used for debugging image output.
	 *
	 * @param  string  $message
	 */
	protected function debug()
	{
		if (isset($this->configuration['debug']) and $this->configuration['debug'])
		{
			$messages = func_get_args();
			foreach ($messages as $message)
			{
				echo "\n" . $message . "\n";
			}
		}
	}

	public function __destruct()
	{
		if (isset($this->image_temp) and file_exists($this->image_temp))
		{
			unlink($this->image_temp);
		}
	}


}