<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// --------------------------------------------------------------------

$plugin_info = array(
	'pi_name' => 'Includee',
	'pi_version' => '1.0',
	'pi_author' => 'Micky Hulse',
	'pi_author_url' => 'http://hulse.me/',
	'pi_description' => '[Expression Engine 2.0] Includee: (Random) PHP[5] include()/readfile(), plus optional caching.',
	'pi_usage' => Mah_includee::usage()
);

// --------------------------------------------------------------------

/**
 * Mah_includee Class
 * 
 * @package       ExpressionEngine
 * @category      Plugin
 * @author        Micky Hulse
 * @copyright     Copyright (c) 2010, Micky Hulse
 * @link          http://hulse.me/
 */
 
class Mah_includee {
	
	//--------------------------------------------------------------------------
	//
	// Configurables (optional):
	//
	//--------------------------------------------------------------------------
	
	private $root_path  = '';                            // Example: '/home/user/public_html', with no trailing slash.
	private $delimiter = ',';                            // Delimiter used for random file inclusions.
	private $extensions = array('php', 'html', 'txt');   // Allowed extensions. Set as an empty string to bypass extension checking.
	private $illegals = array('..', '"', ';');           // Strings to remove from file path. Set as an empty string to bypass path checking.
	private $cache_folder = '';                          // Override the cache folder location?
	
	//--------------------------------------------------------------------------
	//
	// Do not edit past this point.
	//
	//--------------------------------------------------------------------------
	
	// ----------------------------------
	// Constants:
	// ----------------------------------
	
	const CACHE_NAME = 'mah_includee';
	
	// ----------------------------------
	// Public class variables:
	// ----------------------------------
	
	public $return_data = '';
	
	// ----------------------------------
	// Private class variables:
	// ----------------------------------
	
	private $file = '';
	private $cache = FALSE;
	private $cache_refresh = 30;
	private $command = 'readfile';
	
	/**
	 * Constructor
	 *
	 * @access     public
	 * @return     void
	 */
	
	function Mah_includee()
	{
		
		# Performance Guidelines:
		# http://expressionengine.com/public_beta/docs/development/guidelines/performance.html
		# General Style and Syntax:
		# http://expressionengine.com/public_beta/docs/development/guidelines/general.html
		
		// ----------------------------------
		// Call super object:
		// ----------------------------------

		$this->EE =& get_instance();
		
		// ----------------------------------
		// Method variables:
		// ----------------------------------
		
		$fetch = '';
		
		// ----------------------------------
		// Fetch plugin parameters:
		// ----------------------------------
		
		$this->file = ($this->_check_str($fetch = $this->EE->TMPL->fetch_param('file')) === TRUE) ? $fetch : $this->file;
		$this->cache = (strtolower($this->EE->TMPL->fetch_param('cache')) === 'true') ? TRUE : $this->cache;
		$this->cache_refresh = ($this->_is_natural($fetch = $this->EE->TMPL->fetch_param('refresh')) === TRUE) ? $fetch : $this->cache_refresh;
		$this->command = ($this->_check_str($fetch = $this->EE->TMPL->fetch_param('command')) === TRUE) ? $fetch : $this->command;
		
		// ----------------------------------
		// Setup cache folder:
		// ----------------------------------
		
		$this->cache_folder = ($this->_check_str($this->cache_folder) === TRUE) ? $this->cache_folder : APPPATH . 'cache/' . self::CACHE_NAME . '/';
		
		// ----------------------------------
		// Return data:
		// ----------------------------------
		
		$this->return_data = $this->_main();
		
	}
	
	/**
	 * Main
	 * 
	 * @access     private
	 * @return     string
	 */
	
	private function _main()
	{
		
		// ----------------------------------
		// Method variables:
		// ----------------------------------
		
		$data = '';
		$inc = '';
		
		// ----------------------------------
		// Check file parameter:
		// ----------------------------------
		
		if ($this->_check_str($this->file) === TRUE) // 1
		{
			
			// ----------------------------------
			// Randomize?
			// ----------------------------------
			
			if (strpos($this->file, $this->delimiter) !== FALSE) $this->file = $this->_get_rand($this->file, $this->delimiter);
			
			if ($this->_check_str($this->file) === TRUE) // 2
			{
				
				// ----------------------------------
				// Check path?
				// ----------------------------------
				
				if ((is_array($this->illegals) === FALSE) OR ($this->_is_secure_path($this->file, $this->illegals) === TRUE)) // 3
				{
					
					// ----------------------------------
					// Check extension?
					// ----------------------------------
					
					if ((is_array($this->extensions) === FALSE) OR ($this->_is_valid_ext($this->file, $this->extensions) === TRUE)) // 4
					{
						
						// ----------------------------------
						// Determine root path:
						// ----------------------------------
						
						$this->root_path = $this->_doc_root($this->root_path);
						
						if ($this->_check_str($this->root_path) === TRUE) // 5
						{
							
							// ----------------------------------
							// Build include path:
							// ----------------------------------
							
							$inc = trim($this->root_path . $this->file);
							
							// ----------------------------------
							// Clean path:
							// ----------------------------------
							
							$inc = $this->EE->functions->remove_double_slashes($inc);
							$inc = $this->_clean_path($inc);
							
							if ($this->_check_file($inc) === TRUE) // 6
							{
								
								// ----------------------------------
								// Caching?
								// ----------------------------------
								
								if ($this->cache === TRUE)
								{
									
									// ----------------------------------
									// Check/create cache directory:
									// ----------------------------------
									
									if ($this->_check_dir($this->cache_folder) === TRUE)
									{
										
										// ----------------------------------
										// Get cache:
										// ----------------------------------
										
										$data = $this->_cache_get($this->cache_folder, $inc, $this->cache_refresh);
										
									}
									else
									{
										$this->EE->TMPL->log_item('Unable to find, read, and/or create the cache folder');
									}
									
								}
								
								if ($this->_check_str($data) === FALSE) {
									
									// ----------------------------------
									// Read the include:
									// ----------------------------------
									
									$data = $this->_get_ob_data($inc, $this->command);
									
									if (($this->_check_str($data) === TRUE) && ($this->cache === TRUE))
									{
										
										$this->EE->TMPL->log_item('Data retrieved using command: ' . $this->command);
										
										// ----------------------------------
										// Write the cache:
										// ----------------------------------
										
										$this->_cache_write($this->cache_folder, $inc, $data);
									
									}
									
								}
								
							}
							else
							{
								$this->EE->TMPL->log_item('File does not exist'); // 6
							}
							
						}
						else
						{
							$this->EE->TMPL->log_item('Could not determine a root path'); // 5
						}
						
					}
					else
					{
						$this->EE->TMPL->log_item('Extension not allowed'); // 4
					}
					
				}
				else
				{
					$this->EE->TMPL->log_item('Path invalid'); // 3
				}
				
			}
			else
			{
				$this->EE->TMPL->log_item('File randomization failed'); // 2
			}
			
		}
		else
		{
			$this->EE->TMPL->log_item('Invalid "file" parameter'); // 1
		}
		
		// ----------------------------------
		// Return data:
		// ----------------------------------
		
		return $data;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Checks and gets cached data
	 * 
	 * @access     private
	 * @param      string
	 * @return     string
	 */
	
	private function _cache_get($dir = '', $file = '', $refresh = NULL)
	{
		
		// ----------------------------------
		// Method variables:
		// ----------------------------------
		
		$data = '';
		$path = '';
		$fp = '';
		$timestamp = '';
		
		// ----------------------------------
		// Validate method arguments:
		// ----------------------------------
		
		if (($this->_check_str($dir) === TRUE) && ($this->_check_str($file) === TRUE) && ($this->_is_natural($refresh) === TRUE))
		{
			
			$path = $dir . md5($file);
			
			// ----------------------------------
			// Does a cached file already exist?
			// ----------------------------------
			
			if ($this->_check_file($path) === TRUE)
			{
				
				// ----------------------------------
				// Create file pointer:
				// ----------------------------------
				
				$fp = @fopen($path, 'rb');
				
				if ($fp !== FALSE)
				{
					
					flock($fp, LOCK_SH);
					
					// ----------------------------------
					// Get the timestamp:
					// ----------------------------------
					
					$timestamp = trim(fgets($fp, 30));
					
					if (strlen($timestamp) == 10)
					{
						
						// ----------------------------------
						// Is it time to update the cache?
						// ----------------------------------
						
						if (time() < ($timestamp + ($refresh * 60)))
						{
							
							// ----------------------------------
							// Read the file pointer:
							// ----------------------------------
							
							$data = @fread($fp, filesize($file));
							
							if ($data !== FALSE)
							{
								
								// ----------------------------------
								// Success!
								// ----------------------------------
								
								$this->EE->TMPL->log_item('Cache loaded: ' . $file);
								
								$data = trim($data);
								
							}
							else
							{
								$this->EE->TMPL->log_item('Empty Cache File: ' . $file);
							}
							
						}
						
					}
					else
					{
						$this->EE->TMPL->log_item('Corrupt Cache File: ' . $file);
					}
					
					flock($fp, LOCK_UN);
					
					// ----------------------------------
					// Close the file pointer:
					// ----------------------------------
					
					fclose($fp);
					
				}
				
			}
			
		}
		
		// ----------------------------------
		// Return:
		// ----------------------------------
		
		return $data;
		
	}

	// --------------------------------------------------------------------
	
	/**
	 * Write cached data
	 * 
	 * @access     private
	 * @param      string
	 * @param      string
	 * @param      string
	 * @return     boolean
	 */
	
	private function _cache_write($dir = '', $file = '', $data = '')
	{
		
		// ----------------------------------
		// Method variables:
		// ----------------------------------
		
		$return = FALSE;
		$path = '';
		
		// ----------------------------------
		// Validate method arguments:
		// ----------------------------------
		
		if (($this->_check_str($dir) === TRUE) && ($this->_check_str($file) === TRUE) && ($this->_check_str($data) === TRUE))
		{
			
			// ----------------------------------
			// Prepend timestamp:
			// ----------------------------------
			
			$data = time() . "\n" . $data;
			
			// ----------------------------------
			// Write the cached data:
			// ----------------------------------
			
			$path = $dir . md5($file);
			$return = $this->_make_file($path, $data);
			
			if ($return === TRUE) $this->EE->TMPL->log_item('Cache created');
			
		}
		
		// ----------------------------------
		// Return:
		// ----------------------------------
		
		return $return;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Check and/or create folder
	 * 
	 * @access     private
	 * @param      string
	 * @return     boolean
	 */
	
	private function _check_dir($dir = '') {
		
		// ----------------------------------
		// Method variables:
		// ----------------------------------
		
		$return = FALSE;
		
		// ----------------------------------
		// Validate argument:
		// ----------------------------------
		
		if ($this->_check_str($dir) === TRUE)
		{
			
			if (@is_dir($dir) === TRUE) {
				
				// ----------------------------------
				// The directory already exists:
				// ----------------------------------
				
				$return = TRUE;
				
			}
			elseif ($this->_make_dir($dir) === TRUE)
			{
				
				// ----------------------------------
				// Directory created:
				// ----------------------------------
				
				 $return = TRUE;
				
			}
			
		}
		
		// ----------------------------------
		// Return:
		// ----------------------------------
		
		return  $return;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Make directory
	 * 
	 * @access     private
	 * @param      string
	 * @param      integer
	 * @return     boolean
	 */
	
	private function _make_dir($path = '', $mode = 0777)
	{
		
		// ----------------------------------
		// Method variables:
		// ----------------------------------
		
		$return = FALSE;
		
		// ----------------------------------
		// Validate argument:
		// ----------------------------------
		
		if (($this->_check_str($path) === TRUE) && ($this->_is_octal($mode) === TRUE))
		{
			
			// ----------------------------------
			// Attempt to create cache folder:
			// ----------------------------------
			
			$return = (@mkdir($path, $mode) && @chmod($path, $mode));
			
		}
		
		// ----------------------------------
		// Return:
		// ----------------------------------
		
		return $return;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Make file
	 * 
	 * @access     private
	 * @param      string
	 * @param      string
	 * @param      integer
	 * @return     boolean
	 */
	
	private function _make_file($file = '', $data = '', $mode = 0777)
	{
		
		// ----------------------------------
		// Method variables:
		// ----------------------------------
		
		$return = FALSE;
		$fp = FALSE;
		
		// ----------------------------------
		// Validate arguments:
		// ----------------------------------
		
		if (($this->_check_str($file) === TRUE) && ($this->_check_str($data) === TRUE) && ($this->_is_octal($mode) === TRUE))
		{
			
			# What is best way to validate $data?
			# Should I validate it as a string?
			
			// ----------------------------------
			// Attempt to create the file:
			// ----------------------------------
			
			# fopen() returns a file pointer resource on success, or FALSE on error.
			$fp = @fopen($file, 'wb');
			
			if ($fp !== FALSE)
			{
		
				flock($fp, LOCK_EX);
				fwrite($fp, $data);
				flock($fp, LOCK_UN);
				fclose($fp);
				
				# chmod returns TRUE on success or FALSE on failure.
				$return = @chmod($file, $mode);
				
			}
			
		}
		
		// ----------------------------------
		// Return:
		// ----------------------------------
		
		return $return;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Include or read a file using output buffering
	 * 
	 * @access     private
	 * @param      string
	 * @param      string
	 * @return     string
	 */
	
	private function _get_ob_data($file = '', $command = '')
	{
		
		// ----------------------------------
		// Method variables:
		// ----------------------------------
		
		$data = '';
		
		// ----------------------------------
		// Validate argument:
		// ----------------------------------
		
		if (($this->_check_file($file) === TRUE) && ($this->_check_str($command) === TRUE)) {
			
			ob_start();
			switch ($command)
			{
				case 'include':
					@include($file);
					break;
				default:
					@readfile($file);
			}
			$data = ob_get_contents();
			ob_end_clean();
			
		}
		
		// ----------------------------------
		// Return:
		// ----------------------------------
		
		return $data;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Cleans file path
	 * 
	 * @access     private
	 * @param      string
	 * @return     string
	 */
	
	private function _clean_path($path = '')
	{
		
		// ----------------------------------
		// Remove query string:
		// ----------------------------------
		
		if (strpos($path, '?') !== FALSE) $path = strtok($path, '?');
		
		// ----------------------------------
		// Remove double backslashes:
		// ----------------------------------
		
		if (strpos($path, '\\') !== FALSE) $path = str_replace('\\', '/', $path);
		
		// ----------------------------------
		// Return:
		// ----------------------------------
		
		return $path;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Checks path for illegal strings
	 * 
	 * @access     private
	 * @param      string
	 * @param      array
	 * @return     boolean
	 */
	
	private function _is_secure_path($path = '', $illegal)
	{
		$return = FALSE;
		$flag = FALSE;
		
		if (($this->_check_str($path) === TRUE) && (is_array($illegal) === TRUE))
		{
			
			// ----------------------------------
			// Replace invalid strings:
			// ----------------------------------
			
			foreach($illegal as $val)
			{
				
				if (strpos($path, $val) !== FALSE)
				{
					# Illegal bit found:
					$flag = TRUE;
					# Exit loop:
					break;
				}
				
			}
			
			if ($flag !== TRUE) $return = TRUE;
			
		}
		
		// ----------------------------------
		// Return:
		// ----------------------------------
		
		return $return;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Check if a file's extension is in array of allowed extensions
	 * 
	 * @access     private
	 * @param      string
	 * @param      array
	 * @return     boolean
	 */
	
	private function _is_valid_ext($file = '', $allowed)
	{
		
		$return = FALSE;
		
		if (($this->_check_str($file) === TRUE) && (is_array($allowed) === TRUE))
		{
			
			// ----------------------------------
			// Is ext. in array of allowed extensions?
			// ----------------------------------
			
			if (in_array(@pathinfo($file, PATHINFO_EXTENSION), $allowed)) $return = TRUE;
			
		}
		
		// ----------------------------------
		// Return:
		// ----------------------------------
		
		return $return;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Get random key from delimited string
	 * 
	 * @access     private
	 * @param      string
	 * @param      string
	 * @return     string
	 */
	
	private function _get_rand($str = '', $delim = '')
	{
		
		// ----------------------------------
		// Method variables:
		// ----------------------------------
		
		$return = '';
		$array = array();
		$rand_key = 0;
		
		// ----------------------------------
		// Validate arguments:
		// ----------------------------------
		
		if ($this->_check_str($str) && $this->_check_str($delim))
		{
			
			$array = @explode($delim, $str);
			$rand_key = @array_rand($array);
			$return = trim($array[$rand_key]);
			
		}
		
		// ----------------------------------
		// Return:
		// ----------------------------------
		
		return $return;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Returns document root path
	 * 
	 * @access     private
	 * @param      string
	 * @return     string
	 */
	
	private function _doc_root($override = '')
	{
		return ($this->_check_str($override) === TRUE) ? $override : ((array_key_exists('DOCUMENT_ROOT', $_ENV)) ? $_ENV['DOCUMENT_ROOT'] : $_SERVER['DOCUMENT_ROOT']);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Check if file exists and is readable
	 * 
	 * @access     private
	 * @param      string
	 * @return     boolean
	 */
	
	private function _check_file($x = '')
	{
		if ($this->_check_str($x = trim($x)) === TRUE) return (@is_file($x) && @file_exists($x) && @is_readable($x));
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Checks is variable is set and is string
	 * 
	 * @access     private
	 * @param      string
	 * @return     boolean
	 */
	
	private function _check_str($string = '')
	{
		return ((isset($string) === TRUE) && (is_string($string) === TRUE) && (strlen(trim($string)) > 0)) ? TRUE : FALSE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Integer validation using the OCTAL flag (PHP 5 >= 5.2.0)
	 * 
	 * @access     private
	 * @param      mixed
	 * @return     boolean
	 */
	
	private function _is_octal($var = NULL)
	{
		return (filter_var($var, FILTER_VALIDATE_INT, array('flags' => FILTER_FLAG_ALLOW_OCTAL)) === FALSE) ? FALSE : TRUE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Checks if variable a natural number
	 * 
	 * Zero is often exclude from the natural numbers, that's why there's the second parameter.
	 * 
	 * @access     private
	 * @param      string/integer
	 * @param      boolean
	 * @return     boolean
	 */
	
	private function _is_natural($var = NULL, $zero = FALSE)
	{
		return (((string) $var === (string) (int) $var) && (intval($var) < (($zero) ? 0 : 1))) ? FALSE : TRUE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Plugin Usage
	 * 
	 * @access     public
	 * @return     string
	 */
	
	public function usage()
	{
		
		ob_start();
		
		?>
		
		[Expression Engine 2.0] Includee: (Random) PHP[5] include()/readfile(), plus optional caching.
		
		Please see forum thread for more information:
		http://expressionengine.com/forums/viewthread/...
		
		Thanks to Pascal Kriete for the cache code and inspiration:
		http://github.com/pkriete/pk.github.ee_addon
		
		<?php
		
		$buffer = ob_get_contents();
		
		ob_end_clean(); 
		
		return $buffer;
		
	}
	
	// --------------------------------------------------------------------
	
}

/* End of file pi.mah_includee.php */
/* Location: ./system/expressionengine/mah_includee/pi.mah_includee.php */