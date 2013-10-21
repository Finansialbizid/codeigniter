<?php
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.2.4 or newer
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Open Software License version 3.0
 *
 * This source file is subject to the Open Software License (OSL 3.0) that is
 * bundled with this package in the files license.txt / license.rst.  It is
 * also available through the world wide web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to obtain it
 * through the world wide web, please send an email to
 * licensing@ellislab.com so we can send you a copy immediately.
 *
 * @package		CodeIgniter
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2013, EllisLab, Inc. (http://ellislab.com/)
 * @license		http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Parser Class
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Parser
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/parser.html
 */
class CI_Parser {

	/**
	 * Left delimiter character for pseudo vars
	 *
	 * @var string
	 */
	public $l_delim = '{';

	/**
	 * Right delimiter character for pseudo vars
	 *
	 * @var string
	 */
	public $r_delim = '}';

	/**
	 * Reference to CodeIgniter instance
	 *
	 * @var object
	 */
	protected $CI;

	// --------------------------------------------------------------------

	/**
	 * Class constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->CI =& get_instance();
	}

	// --------------------------------------------------------------------

	/**
	 * Parse a template
	 *
	 * Parses pseudo-variables contained in the specified template view,
	 * replacing them with the data in the second param
	 *
	 * @param	string
	 * @param	array
	 * @param	bool
	 * @return	string
	 */
	public function parse($template, $data, $return = FALSE)
	{
		$template = $this->CI->load->view($template, $data, TRUE);

		return $this->_parse($template, $data, $return);
	}

	// --------------------------------------------------------------------

	/**
	 * Parse a String
	 *
	 * Parses pseudo-variables contained in the specified string,
	 * replacing them with the data in the second param
	 *
	 * @param	string
	 * @param	array
	 * @param	bool
	 * @return	string
	 */
	public function parse_string($template, $data, $return = FALSE)
	{
		return $this->_parse($template, $data, $return);
	}

	// --------------------------------------------------------------------

	/**
	 * Parse a template
	 *
	 * Parses pseudo-variables contained in the specified template,
	 * replacing them with the data in the second param
	 *
	 * @param	string
	 * @param	array
	 * @param	bool
	 * @return	string
	 */
	protected function _parse($template, $data, $return = FALSE)
	{
		if ($template === '')
		{
			return FALSE;
		}

		foreach ($data as $key => $val)
		{
			if (is_object($val))
			{
				$template = $this->_parse_object($key, $val, $template);
			}else if (is_array($val))
			{
				$template = $this->_parse_pair($key, $val, $template);
			}
			else
			{
				$template = $this->_parse_single($key, (string)$val, $template);
			}
		}

		if ($return === FALSE)
		{
			$this->CI->output->append_output($template);
		}

		return $template;
	}

	// --------------------------------------------------------------------

	/**
	 * Set the left/right variable delimiters
	 *
	 * @param	string
	 * @param	string
	 * @return	void
	 */
	public function set_delimiters($l = '{', $r = '}')
	{
		$this->l_delim = $l;
		$this->r_delim = $r;
	}

	// --------------------------------------------------------------------

	/**
	 * Parse a single key/value
	 *
	 * @param	string
	 * @param	string
	 * @param	string
	 * @return	string
	 */
	protected function _parse_single($key, $val, $string)
	{
		return str_replace($this->l_delim.$key.$this->r_delim, (string) $val, $string);
	}

	// --------------------------------------------------------------------

	/**
	 * Parse a tag pair
	 *
	 * Parses tag pairs: {some_tag} string... {/some_tag}
	 *
	 * @param	string
	 * @param	array
	 * @param	string
	 * @return	string
	 */
	protected function _parse_pair($variable, $data, $string)
	{
		if (FALSE === ($match = $this->_match_pair($string, $variable)))
		{
			return $string;
		}

		$str = '';
		foreach ($data as $row)
		{
			$temp = $match[1];
			foreach ($row as $key => $val)
			{
				$temp = is_array($val)
						? $this->_parse_pair($key, $val, $temp)
						: $this->_parse_single($key, $val, $temp);
			}

			$str .= $temp;
		}

		return str_replace($match[0], $str, $string);
	}

	/**
	*  Parse an Object-Method-Call
	*
	* Sample:  {object:method}
	*
	* @access  private
	* @param   string
	* @param   object
	* @param   string
	* @return  string
	*/
	function _parse_object($key, $val, $template)
	{
		preg_match_all("|" . preg_quote($this->l_delim) . $key . ":" . "(.+?)" . preg_quote($this->r_delim) . "|", $template, $match);
	
		$i = -1;
		while( ++$i < count($match[0]) )
		{
		    $key    = $match[0][$i];
		    $source = $match[1][$i];
		
		    if( method_exists($val, $source) )
		    {
		        $template = str_replace($key, $val->$source(), $template);
		    }
		    else if( property_exists($val, $source) )
		    {
		        $template = str_replace($key, $val->$source, $template);
		    }
		}
		
		return $template;
	}


	// --------------------------------------------------------------------

	/**
	 * Matches a variable pair
	 *
	 * @param	string
	 * @param	string
	 * @return	mixed
	 */
	protected function _match_pair($string, $variable)
	{
		return preg_match('|'.preg_quote($this->l_delim).$variable.preg_quote($this->r_delim).'(.+?)'.preg_quote($this->l_delim).'/'.$variable.preg_quote($this->r_delim).'|s',
					$string, $match)
			? $match : FALSE;
	}

}

/* End of file Parser.php */
/* Location: ./system/libraries/Parser.php */
