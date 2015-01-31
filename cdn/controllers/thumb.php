<?php

//	Include _cdn.php; executes common functionality
require_once '_cdn.php';

/**
 * This class handles the "thumb" CDN endpoint
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

class NAILS_Thumb extends NAILS_CDN_Controller
{
	protected $_fail;
	protected $_bucket;
	protected $_width;
	protected $_height;
	protected $_object;
	protected $_extension;
	protected $_cache_file;


	// --------------------------------------------------------------------------


    /**
     * Construct the class; set defaults
     *
     * @access    public
     * @return    void
     *
     **/
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //	Determine dynamic values
        $this->_width     = $this->uri->segment(3, 100);
        $this->_height    = $this->uri->segment(4, 100);
        $this->_bucket    = $this->uri->segment(5);
        $this->_object    = urldecode($this->uri->segment(6));
        $this->_extension = !empty($this->_object) ? strtolower(substr($this->_object, strrpos($this->_object, '.'))) : false;

        // --------------------------------------------------------------------------

        /**
         * Test for Retina - @2x just now, add more options as pixel densities
         * become higher.
         */

        if (preg_match('/(.+)@2x(\..+)/', $this->_object, $matches)) {

            $this->isRetina         = true;
            $this->retinaMultiplier = 2;
            $this->_object          = $matches[1] . $matches[2];
        }
    }


	// --------------------------------------------------------------------------


	/**
	 * Generate the thumbnail
	 *
	 * @access	public
	 * @return	void
	 **/
	public function index($crop_method = 'THUMB')
	{
		//	Sanitize the crop method
		$_cropmethod = strtoupper($crop_method);

		switch ($_cropmethod) :

			case 'SCALE'	:	$_phpthumb_method = 'resize';			break;
			case 'THUMB'	:
			default			:	$_phpthumb_method = 'adaptiveResize';	break;

		endswitch;

		// --------------------------------------------------------------------------

		//	Define the cache file
		$width	= $this->_width * $this->retinaMultiplier;
		$height	= $this->_height * $this->retinaMultiplier;

		$this->cdnCacheFile  = $this->_bucket;
		$this->cdnCacheFile .= '-' . substr($this->_object, 0, strrpos($this->_object, '.'));
		$this->cdnCacheFile .= '-' . $_cropmethod;
		$this->cdnCacheFile .= '-' . $width . 'x' . $height;
		$this->cdnCacheFile .= $this->_extension;

		// --------------------------------------------------------------------------

		//	We must have a bucket, object and extension in order to work with this
		if (!$this->_bucket || !$this->_object || !$this->_extension) :

			log_message('error', 'CDN: ' . $_cropmethod . ': Missing _bucket, _object or _extension');
			return $this->serveBadSrc();

		endif;

		// --------------------------------------------------------------------------

		//	Check the request headers; avoid hitting the disk at all if possible. If the Etag
		//	matches then send a Not-Modified header and terminate execution.

		if ($this->serveNotModified($this->cdnCacheFile)) :

			$this->cdn->object_increment_count($_cropmethod, $this->_object, $this->_bucket);
			return;

		endif;

		// --------------------------------------------------------------------------

		$object = $this->cdn->get_object($this->_object, $this->_bucket);

		if (!$object) {

			/**
			 * If trashed=1 GET param is set and user is a logged in admin with
			 * can_browse_trash permission then have a look in the trash
			 */

			if ($this->input->get('trashed') && user_has_permission('admin.cdnadmin:0.can_browse_trash')) {

				$object = $this->cdn->get_object_from_trash($this->_object, $this->_bucket);

				if (!$object) {

					//	Cool, guess it really doesn't exist
					$width	= $this->_width * $this->retinaMultiplier;
					$height	= $this->_height * $this->retinaMultiplier;

					return $this->serveBadSrc($width, $height);
				}

			} else {

				$width	= $this->_width * $this->retinaMultiplier;
				$height	= $this->_height * $this->retinaMultiplier;

				return $this->serveBadSrc($width, $height);
			}
		}

		// --------------------------------------------------------------------------

		/**
		 * The browser does not have a local cache (or it's out of date) check the
		 * cache to see if this image has been processed already; serve it up if
		 * it has.
		 */

		if (file_exists($this->cdnCacheDir . $this->cdnCacheFile)) :

			$this->cdn->object_increment_count($_cropmethod, $this->_object, $this->_bucket);
			$this->serveFromCache($this->cdnCacheFile);

		else :

			/**
			 * Cache object does not exist, fetch the original, process it and save a
			 * version in the cache bucket.
			 */

			//	Fetch the file to use
			$_usefile = $this->cdn->object_local_path($this->_bucket, $this->_object);

			if (!$_usefile) :

				log_message('error', 'CDN: ' . $_cropmethod . ': No local path was returned.');
				log_message('error', 'CDN: ' . $_cropmethod . ': ' . $this->cdn->last_error());

				$width	= $this->_width * $this->retinaMultiplier;
				$height	= $this->_height * $this->retinaMultiplier;

				return $this->serveBadSrc($width, $height);

			elseif(!filesize($_usefile)) :

				/**
				 * Hmm, empty, delete it and try one more time
				 * @TODO: work out the reason why we do this
				 */

				@unlink($_usefile);

				$_usefile = $this->cdn->object_local_path($this->_bucket, $this->_object);

				if (!$_usefile) :

					log_message('error', 'CDN: ' . $_cropmethod . ': No local path was returned, second attempt.');
					log_message('error', 'CDN: ' . $_cropmethod . ': ' . $this->cdn->last_error());

					$width	= $this->_width * $this->retinaMultiplier;
					$height	= $this->_height * $this->retinaMultiplier;

					return $this->serveBadSrc($width, $height);

				elseif(!filesize($_usefile)) :

					log_message('error', 'CDN: ' . $_cropmethod . ': local path exists, but has a zero filesize.');

					$width	= $this->_width * $this->retinaMultiplier;
					$height	= $this->_height * $this->retinaMultiplier;

					return $this->serveBadSrc($width, $height);

				endif;

			endif;

			// --------------------------------------------------------------------------

			/**
			 * Time to start Image processing
			 * Are we dealing with an animated Gif? If so handle differently - extract each
			 * frame, resize, then recompile. Otherwise, just resize
			 */

			//	Set the appropriate cache headers
			$this->setCacheHeaders(time(), $this->cdnCacheFile, FALSE);

			// --------------------------------------------------------------------------

			//	Handle the actual resize
			if ($object->is_animated) {

				$this->_resize_animated($_usefile, $_phpthumb_method);

			} else {

				$this->_resize($_usefile, $_phpthumb_method);
			}

			// --------------------------------------------------------------------------

			//	Bump the counter
			$this->cdn->object_increment_count($_cropmethod, $object->id);

		endif;
	}


	// --------------------------------------------------------------------------


	private function _resize($usefile, $PHPThumb_method)
	{
		//	Set some PHPThumb options
		$_options					= array();
		$_options['resizeUp']		= true;
		$_options['jpegQuality']	= 80;

		// --------------------------------------------------------------------------

		/**
		 * Perform the resize
		 * Turn errors off, if something bad happens we want to
		 * output the serveBadSrc image and log the issue.
		 */

		$_old_errors = error_reporting();
		error_reporting(0);

		$width	= $this->_width * $this->retinaMultiplier;
		$height	= $this->_height * $this->retinaMultiplier;
		$ext	= strtoupper(substr($this->_extension, 1));

		if ($ext === 'JPEG') {
			$ext = 'jpg';
		}

		try
		{
			/**
			 * Catch any output, don't want anything going to the browser unless
			 * we're sure it's ok
			 */

			ob_start();

			$PHPThumb = new PHPThumb\GD($usefile, $_options);
			$PHPThumb->{$PHPThumb_method}($width, $height);

			//	Save cache version
			$PHPThumb->save($this->cdnCacheDir . $this->cdnCacheFile, $ext);

			//	Flush the buffer
			ob_end_clean();
		}
		catch(Exception $e)
		{
			//	Log the error
			log_message('error', 'CDN: ' . $PHPThumb_method . ': ' . $e->getMessage());

			//	Switch error reporting back how it was
			error_reporting($_old_errors);

			//	Flush the buffer
			ob_end_clean();

			//	Bad SRC
			return $this->serveBadSrc($width, $height);
		}

		$this->serveFromCache($this->cdnCacheFile, false);

		//	Switch error reporting back how it was
		error_reporting($_old_errors);
	}


	// --------------------------------------------------------------------------


	private function _resize_animated($usefile, $PHPThumb_method)
	{
		$_hash			= md5(microtime(TRUE) . uniqid()) . uniqid();
		$_frames		= array();
		$_cachefiles	= array();
		$_durations		= array();
		$_gfe			= new GifFrameExtractor\GifFrameExtractor();
		$_gc			= new GifCreator\GifCreator();
		$width			= $this->_width * $this->retinaMultiplier;
		$height			= $this->_height * $this->retinaMultiplier;

		// --------------------------------------------------------------------------

		//	Extract all the frames, resize them and save to the cache
		$_gfe->extract($usefile);

		$_i = 0;
		foreach ($_gfe->getFrames() as $frame) :

			//	Define the filename
			$_filename		= $_hash . '-' . $_i . '.gif';
			$_temp_filename	= $_hash . '-' . $_i . '-original.gif';
			$_i++;

			//	Set these for recompiling
			$_frames[]		= $this->cdnCacheDir . $_filename;
			$_cachefiles[]	= $this->cdnCacheDir . $_temp_filename;
			$_durations[]	= $frame['duration'];

			// --------------------------------------------------------------------------

			//	Set some PHPThumb options
			$_options					= array();
			$_options['resizeUp']		= TRUE;

			// --------------------------------------------------------------------------

			//	Perform the resize; first save the original frame to disk
			imagegif($frame['image'], $this->cdnCacheDir . $_temp_filename);

			$PHPThumb = new PHPThumb\GD($this->cdnCacheDir . $_temp_filename, $_options);
			$PHPThumb->{$PHPThumb_method}($width, $height);

			// --------------------------------------------------------------------------

			//	Save cache version
			$PHPThumb->save($this->cdnCacheDir . $_filename , strtoupper(substr($this->_extension, 1)));

		endforeach;

		/**
		 * Recompile the resized images back into an animated gif and save to the cache
		 * TODO: We assume the gif loops infinitely but we should really check.
		 * Issue made on the library's GitHub asking for this feature.
		 * View here: https://github.com/Sybio/GifFrameExtractor/issues/3
		 */

		$_gc->create($_frames, $_durations, 0);
		$_data = $_gc->getGif();

		// --------------------------------------------------------------------------

		//	Output to browser
		header('Content-Type: image/gif', TRUE);
		echo $_data;

		// --------------------------------------------------------------------------

		//	Save to cache
		$this->load->helper('file');
		write_file($this->cdnCacheDir . $this->cdnCacheFile, $_data);

		// --------------------------------------------------------------------------

		//	Remove cache frames
		foreach ($_frames as $frame) :

			@unlink($frame);

		endforeach;

		foreach ($_cachefiles as $frame) :

			@unlink($frame);

		endforeach;

		// --------------------------------------------------------------------------

		/**
		 * Kill script, th, th, that's all folks.
		 * Stop the output class from hijacking our headers and
		 * setting an incorrect Content-Type
		 */

		exit(0);
	}


	// --------------------------------------------------------------------------


	public function _remap()
	{
		$this->index();
	}
}


// --------------------------------------------------------------------------


/**
 * OVERLOADING NAILS' CDN MODULES
 *
 * The following block of code makes it simple to extend one of the core CDN
 * controllers. Some might argue it's a little hacky but it's a simple 'fix'
 * which negates the need to massively extend the CodeIgniter Loader class
 * even further (in all honesty I just can't face understanding the whole
 * Loader class well enough to change it 'properly').
 *
 * Here's how it works:
 *
 * CodeIgniter instantiate a class with the same name as the file, therefore
 * when we try to extend the parent class we get 'cannot redeclare class X' errors
 * and if we call our overloading class something else it will never get instantiated.
 *
 * We solve this by prefixing the main class with NAILS_ and then conditionally
 * declaring this helper class below; the helper gets instantiated et voila.
 *
 * If/when we want to extend the main class we simply define NAILS_ALLOW_EXTENSION_CLASSNAME
 * before including this PHP file and extend as normal (i.e in the same way as below);
 * the helper won't be declared so we can declare our own one, app specific.
 *
 **/

if (!defined('NAILS_ALLOW_EXTENSION_THUMB')) :

	class Thumb extends NAILS_Thumb
	{
	}

endif;
