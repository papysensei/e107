<?php
/*
* e107 website system
*
* Copyright (C) 2008-2011 e107 Inc (e107.org)
* Released under the terms and conditions of the
* GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
*
* Text processing and parsing functions
*
* $URL$
* $Id$
*
*/

/**
 * @package e107
 * @subpackage e107_handlers
 * @version $Id$
 *
 * Text processing and parsing functions.
 * Simple parse data model.
 */

if (!defined('e107_INIT')) { exit(); }

// Directory for the hard-coded utf-8 handling routines
define('E_UTF8_PACK', e_HANDLER.'utf8/');

define("E_NL", chr(2));

class e_parse extends e_parser
{
	/**
	 * Determine how to handle utf-8.
	 *    0 = 'do nothing'
	 *    1 = 'use mb_string'
	 *    2 = emulation
	 *
	 * @var integer
	 */
	protected $utfAction;

	// Shortcode processor - see __get()
	//var $e_sc;

	// BBCode processor
	var $e_bb;

	// Profanity filter
	var $e_pf;

	// Emote filter
	var $e_emote;

	// 'Hooked' parsers (array)
	var $e_hook;

	var $search = array('&amp;#039;', '&#039;', '&#39;', '&quot;', 'onerror', '&gt;', '&amp;quot;', ' & ');

	var $replace = array("'", "'", "'", '"', 'one<i></i>rror', '>', '"', ' &amp; ');

	// Set to TRUE or FALSE once it has been calculated
	var $e_highlighting;

	// Highlight query
	var $e_query;
	
	public $thumbWidth = 100;
	
	public $thumbHeight = 0;
	
	public $thumbCrop = 0;

	// Set up the defaults
	var $e_optDefault = array(
		// default context: reflects legacy settings (many items enabled)
		'context' 		=> 'OLDDEFAULT',
		//
		'fromadmin' 	=> FALSE,

		// Enable emote display
		'emotes'		=> TRUE,

		// Convert defines(constants) within text.
		'defs' 			=> FALSE,

		// replace all {e_XXX} constants with their e107 value - 'rel' or 'abs'
		'constants' 	=> FALSE,

		// Enable hooked parsers
		'hook'			=> TRUE,

		// Allow scripts through (new for 0.8)
		'scripts'		=> TRUE,

		// Make links clickable
		'link_click'	=> TRUE,

		// Substitute on clickable links (only if link_click == TRUE)
		'link_replace'	=> TRUE,

		// Parse shortcodes - TRUE enables parsing

		'parse_sc' 		=> FALSE,
		// remove HTML tags.
		'no_tags' 		=> FALSE,

		// Restore entity form of quotes and such to single characters - TRUE disables
		'value'			=> FALSE,

		// Line break compression - TRUE removes newline characters
		'nobreak' 		=> FALSE,

		// Retain newlines - wraps to \n instead of <br /> if TRUE (for non-HTML email text etc)
		'retain_nl' 	=> FALSE
		);

	// Super modifiers override default option values
	var	$e_SuperMods = array(
				//text is part of a title (e.g. news title)
				'TITLE' =>
					array(
						'nobreak'=>TRUE, 'retain_nl'=>TRUE, 'link_click' => FALSE, 'emotes'=>FALSE, 'defs'=>TRUE, 'parse_sc'=>TRUE
						),
				'TITLE_PLAIN' =>
					array(
						'nobreak'=>TRUE, 'retain_nl'=>TRUE, 'link_click' => FALSE, 'emotes'=>FALSE, 'defs'=>TRUE, 'parse_sc'=>TRUE, 'no_tags' => TRUE
						),
				//text is user-entered (i.e. untrusted) and part of a title (e.g. forum title)
				'USER_TITLE' =>
					array(
						'nobreak'=>TRUE, 'retain_nl'=>TRUE, 'link_click' => FALSE, 'scripts' => FALSE, 'emotes'=>FALSE, 'hook'=>FALSE
						),
				// text is 'body' of email or similar - being sent 'off-site' so don't rely on server availability
				'E_TITLE' =>
					array(
						'nobreak'=>TRUE, 'retain_nl'=>TRUE, 'defs'=>TRUE, 'parse_sc'=>TRUE, 'emotes'=>FALSE, 'scripts' => FALSE, 'link_click' => FALSE
						),
				// text is part of the summary of a longer item (e.g. content summary)
				'SUMMARY' =>
					array(
						'defs'=>TRUE, 'constants'=>'full', 'parse_sc'=>TRUE
						),
				// text is the description of an item (e.g. download, link)
				'DESCRIPTION' =>
					array(
						'defs'=>TRUE, 'constants'=>'full', 'parse_sc'=>TRUE
						),
				// text is 'body' or 'bulk' text (e.g. custom page body, content body)
				'BODY' =>
					array(
						'defs'=>TRUE, 'constants'=>'full', 'parse_sc'=>TRUE
						),
				'WYSIWYG' =>
					array(
						'defs'=>FALSE, 'constants'=>'full', 'parse_sc'=>FALSE, 'wysiwyg'=>TRUE
						),
				// text is user-entered (i.e. untrusted)'body' or 'bulk' text (e.g. custom page body, content body)
				'USER_BODY' =>
					array(
						'constants'=>'full', 'scripts' => FALSE, 'nostrip'=>FALSE
						),
				// text is 'body' of email or similar - being sent 'off-site' so don't rely on server availability
				'E_BODY' =>
					array(
						'defs'=>TRUE, 'constants'=>'full', 'parse_sc'=>TRUE, 'emotes'=>FALSE, 'scripts' => FALSE, 'link_click' => FALSE
						),
				// text is text-only 'body' of email or similar - being sent 'off-site' so don't rely on server availability
				'E_BODY_PLAIN' =>
					array(
						'defs'=>TRUE, 'constants'=>'full', 'parse_sc'=>TRUE, 'emotes'=>FALSE, 'scripts' => FALSE, 'link_click' => FALSE, 'retain_nl' => TRUE, 'no_tags' => TRUE
						),
				// text is the 'content' of a link (A tag, etc)
				'LINKTEXT' =>
					array(
						'nobreak'=>TRUE, 'retain_nl'=>TRUE, 'link_click' => FALSE, 'emotes'=>FALSE, 'hook'=>FALSE, 'defs'=>TRUE, 'parse_sc'=>TRUE
						),
				// text is used (for admin edit) without fancy conversions or html.
				'RAWTEXT' =>
					array(
						'nobreak'=>TRUE, 'retain_nl'=>TRUE, 'link_click' => FALSE, 'emotes'=>FALSE, 'hook'=>FALSE, 'no_tags'=>TRUE
						)
		);

	// Individual modifiers change the current context
	var $e_Modifiers = array(
				'emotes_off'	=> array('emotes' => FALSE),
				'emotes_on'		=> array('emotes' => TRUE),
				'no_hook'		=> array('hook' => FALSE),
				'do_hook'		=> array('hook' => TRUE),
				// New for 0.8
				'scripts_off'	=> array('scripts' => FALSE),
				// New for 0.8
				'scripts_on'	=> array('scripts' => TRUE),
				'no_make_clickable' => array('link_click' => FALSE),
				'make_clickable' => array('link_click' => TRUE),
				'no_replace' 	=> array('link_replace' => FALSE),
				// Replace text of clickable links (only if make_clickable option set)
				'replace' 		=> array('link_replace' => TRUE),
				// No path replacement
				'consts_off'	=> array('constants' => FALSE),
				// Relative path replacement
				'consts_rel'	=> array('constants' => 'rel'),
				// Absolute path replacement
				'consts_abs'	=> array('constants' => 'abs'),
				// Full path replacement
				'consts_full'	=> array('constants' => 'full'),
				// No shortcode parsing
				'scparse_off'	=> array('parse_sc'	=> FALSE),

				'scparse_on'	=> array('parse_sc'	=> TRUE),
				// Strip tags
				'no_tags' 		=> array('no_tags' 	=> TRUE),
				// Leave tags
				'do_tags' 		=> array('no_tags' 	=> FALSE),

				'fromadmin'		=> array('fromadmin' => TRUE),
				'notadmin'		=> array('fromadmin' => FALSE),
				// entity replacement
				'er_off'		=> array('value' => FALSE),
				'er_on'			=> array('value' => TRUE),
				// Decode constant if exists
				'defs_off'		=> array('defs' => FALSE),
				'defs_on'		=> array('defs' => TRUE),

				'dobreak'		=> array('nobreak' => FALSE),
				'nobreak'		=> array('nobreak' => TRUE),
				// Line break using \n
				'lb_nl'			=> array('retain_nl' => TRUE),
				// Line break using <br />
				'lb_br'			=> array('retain_nl' => FALSE),

				// Legacy option names below here - discontinue later
				'retain_nl'		=> array('retain_nl' => TRUE),
				'defs'			=> array('defs' => TRUE),
				'parse_sc'		=> array('parse_sc'	=> TRUE),
				'constants'		=> array('constants' => 'rel'),
				'value'			=> array('value' => TRUE),
				'wysiwyg'		=> array('wysiwyg'=>TRUE)
		);


	/**
	 * Constructor - keep it public for backward compatibility
	 still some new e_parse() in the core
	 *
	 * @return void
	 */
	public function __construct()
	{
		// initialise the type of UTF-8 processing methods depending on PHP version and mb string extension
		$this->init();
		$this->initCharset();

		// Preprocess the supermods to be useful default arrays with all values
		foreach ($this->e_SuperMods as $key => $val)
		{
			// precalculate super defaults
			$this->e_SuperMods[$key] = array_merge($this->e_optDefault , $this->e_SuperMods[$key]);
			$this->e_SuperMods[$key]['context'] = $key;
		}
	}


	/**
	 * Initialise the type of UTF-8 processing methods depending on PHP version and mb string extension.
	 *
	 * NOTE: can't be called until CHARSET is known
	 but we all know that it is UTF-8 now
	 *
	 * @return void
	 */
	private function initCharset()
	{
		// Start by working out what, if anything, we do about utf-8 handling.
		// 'Do nothing' is the simple option
		$this->utfAction = 0;
// CHARSET is utf-8
//		if(strtolower(CHARSET) == 'utf-8')
//		{
			if(version_compare(PHP_VERSION, '6.0.0') < 1)
			{
				// Need to do something here
				if(extension_loaded('mbstring'))
				{
					// Check for function overloading
					$temp = ini_get('mbstring.func_overload');
					// Just check the string functions - will be non-zero if overloaded
					if(($temp & MB_OVERLOAD_STRING) == 0)
					{
						// Can use the mb_string routines
						$this->utfAction = 1;
					}
					// Set the default encoding, so we don't have to specify every time
					mb_internal_encoding('UTF-8');
				}
				else
				{
					// Must use emulation - will probably be slow!
					$this->utfAction = 2;
					require (E_UTF8_PACK.'utils/unicode.php');
					// Always load the core routines - bound to need some of them!
					require (E_UTF8_PACK.'native/core.php');
				}
			}
//		}
	}


	/**
	 * Unicode (UTF-8) analogue of standard @link http://php.net/strlen strlen PHP function.
	 * Returns the length of the given string.
	 *
	 * @param string $str The UTF-8 encoded string being measured for length.
	 * @return integer The length (amount of UTF-8 characters) of the string on success, and 0 if the string is empty.
	 */
	public function ustrlen($str)
	{
		switch($this->utfAction)
		{
			case 0:
				return strlen($str);
			case 1:
				return mb_strlen($str);
		}
		// Default case shouldn't happen often
		// Save a call - invoke the function directly
		return strlen(utf8_decode($str));
	}


	/**
	 * Unicode (UTF-8) analogue of standard @link http://php.net/strtolower strtolower PHP function.
	 * Make a string lowercase.
	 *
	 * @param string $str The UTF-8 encoded string to be lowercased.
	 * @return string Specified string with all alphabetic characters converted to lowercase.
	 */
	public function ustrtolower($str)
	{
		switch($this->utfAction)
		{
			case 0:
				return strtolower($str);
			case 1:
				return mb_strtolower($str);
		}
		// Default case shouldn't happen often
		return utf8_strtolower($str);
	}


	/**
	 * Unicode (UTF-8) analogue of standard @link http://php.net/strtoupper strtoupper PHP function.
	 * Make a string uppercase.
	 *
	 * @param string $str The UTF-8 encoded string to be uppercased.
	 * @return string Specified string with all alphabetic characters converted to uppercase.
	 */
	public function ustrtoupper($str)
	{
		switch($this->utfAction)
		{
			case 0:
				return strtoupper($str);
			case 1:
				return mb_strtoupper($str);
		}
		// Default case shouldn't happen often
		return utf8_strtoupper($str);
	}


	/**
	 * Unicode (UTF-8) analogue of standard @link http://php.net/strpos strpos PHP function.
	 * Find the position of the first occurrence of a case-sensitive UTF-8 encoded string.
	 * Returns the numeric position (offset in amount of UTF-8 characters)
	 *  of the first occurrence of needle in the haystack string.
	 *
	 * @param string $haystack The UTF-8 encoded string being searched in.
	 * @param integer $needle The UTF-8 encoded string being searched for.
	 * @param integer $offset [optional] The optional offset parameter allows you to specify which character in haystack to start searching.
	 * 				 The position returned is still relative to the beginning of haystack.
	 * @return integer|boolean Returns the position as an integer. If needle is not found, the function will return boolean FALSE.
	 */
	public function ustrpos($haystack, $needle, $offset = 0)
	{
		switch($this->utfAction)
		{
			case 0:
				return strpos($haystack, $needle, $offset);
			case 1:
				return mb_strpos($haystack, $needle, $offset);
		}
		return utf8_strpos($haystack, $needle, $offset);
	}


	/**
	 * Unicode (UTF-8) analogue of standard @link http://php.net/strrpos strrpos PHP function.
	 * Find the position of the last  occurrence of a case-sensitive UTF-8 encoded string.
	 * Returns the numeric position (offset in amount of UTF-8 characters)
	 *  of the last occurrence of needle in the haystack string.
	 *
	 * @param string $haystack The UTF-8 encoded string being searched in.
	 * @param integer $needle The UTF-8 encoded string being searched for.
	 * @param integer $offset [optional] - The optional offset parameter allows you to specify which character in haystack to start searching.
	 * 				 The position returned is still relative to the beginning of haystack.
	 * @return integer|boolean Returns the position as an integer. If needle is not found, the function will return boolean FALSE.
	 */
	public function ustrrpos($haystack, $needle, $offset = 0)
	{
		switch($this->utfAction)
		{
			case 0:
				return strrpos($haystack, $needle, $offset);
			case 1:
				return mb_strrpos($haystack, $needle, $offset);
		}
		return utf8_strrpos($haystack, $needle, $offset);
	}


	/**
	 * Unicode (UTF-8) analogue of standard @link http://php.net/stristr stristr PHP function.
	 * Returns all of haystack starting from and including the first occurrence of needle to the end.
	 *
	 * @param string $haystack The UTF-8 encoded string to search in.
	 * @param mixed $needle If needle is not a string, it is converted to an integer and applied as the ordinal value of a character.
	 * @param integer $length [optional] (PHP 5.3+) If TRUE, returns the part of the haystack before the first occurrence of the needle (excluding needle).
	 * @return string Returns the matched substring. If needle is not found, returns FALSE.
	 */
	public function ustristr($haystack, $needle, $before_needle = false)
	{
		switch($this->utfAction)
		{
			case 0:
				return stristr($haystack, $needle, $before_needle);
			case 1:
				return mb_substr($haystack, $needle, $before_needle);
		}
		// No utf8 pack backup
		return stristr($str, $start, $length);
	}
	
	/**
	 * Unicode (UTF-8) analogue of standard @link http://php.net/substr substr PHP function.
	 * Returns the portion of string specified by the start and length parameters.
	 *
	 * NOTE: May be subtle differences in return values dependent on which routine is used.
	 *  Native substr() routine can return FALSE. mb_substr() and utf8_substr() just return an empty string.
	 *
	 * @param string $str The UTF-8 encoded string.
	 * @param integer $start Start of portion to be returned. Position is counted in amount of UTF-8 characters from the beginning of str.
	 * 				First character's position is 0. Second character position is 1, and so on.
	 * @param integer $length [optional] If length is given, the string returned will contain at most length characters beginning from start
	 * 				(depending on the length of string). If length is omitted, the rest of string from start will be returned.
	 * @return string The extracted UTF-8 encoded part of input string.
	 */
	public function usubstr($str, $start, $length = NULL)
	{
		switch($this->utfAction)
		{
			case 0:
				return substr($str, $start, $length);
			case 1:
				if(is_null($length))
				{
					return mb_substr($str, $start);
				}
				else
				{
					return mb_substr($str, $start, $length);
				}
		}
		return utf8_substr($str, $start, $length);
	}

	/**
	 * Converts the supplied text (presumed to be from user input) to a format suitable for storing in a database table.
	 *
	 * @param string $data
	 * @param boolean $nostrip [optional] Assumes all data is GPC ($_GET, $_POST, $_COOKIE) unless indicate otherwise by setting this var to TRUE.
	 * 				If magic quotes is enabled on the server and you do not tell toDB() that the data is non GPC then slashes will be stripped when they should not be.
	 * @param boolean $no_encode [optional] This parameter should nearly always be FALSE. It is used by the save_prefs() function to preserve HTML content within prefs even when
	 * 				the save_prefs() function has been called by a non admin user / user without html posting permissions.
	 * @param boolean $mod [optional] The 'no_html' and 'no_php' modifiers blanket prevent HTML and PHP posting regardless of posting permissions. (used in logging)
	 *		The 'pReFs' value is for internal use only, when saving prefs, to prevent sanitisation of HTML.
	 * @param boolean $original_author [optional]
	 * @return string
	 * @todo complete the documentation of this essential method
	 */
	public function toDB($data, $nostrip = FALSE, $no_encode = FALSE, $mod = FALSE, $original_author = FALSE)
	{
		
		$core_pref = e107::getConfig();
		if (is_array($data))
		{
			foreach ($data as $key => $var)
			{
				//Fix - sanitize keys as well
				$ret[$this->toDB($key, $nostrip, $no_encode, $mod, $original_author)] = $this->toDB($var, $nostrip, $no_encode, $mod, $original_author);
			}
			return $ret;
		}

	

		if (MAGIC_QUOTES_GPC == TRUE && $nostrip == FALSE)
		{
			$data = stripslashes($data);
		}

		if ($mod != 'pReFs') //XXX We're not saving prefs. 
		{
			$data = $this->preFilter($data); // used by bb_xxx.php toDB() functions. bb_code.php toDB() allows us to properly bypass HTML cleaning below. 
			
			if (strip_tags($data) != $data) // html tags present. 
			{
			//	return $data;
				$data = $this->cleanHtml($data); // sanitize all html. 
				
				$data = urldecode($data); // symptom of cleaning the HTML - urlencodes src attributes containing { and } .eg. {e_BASE} 
			}
			
			if (!check_class($core_pref->get('post_html', e_UC_MAINADMIN)))
			{
				$data = strip_tags($data); // remove tags from cleaned html. 
				$data = str_replace(array('[html]','[/html]'),'',$data); 
			//	$data = $this->dataFilter($data);
			}

			$data = html_entity_decode($data, ENT_QUOTES, 'utf-8');	// Prevent double-entities. Fix for [code]  - see bb_code.php toDB(); 
		}
	
		if (check_class($core_pref->get('post_html'))) /*$core_pref->is('post_html') && */
		{
			$no_encode = TRUE;
		}
				
		if (is_numeric($original_author) && !check_class($core_pref->get('post_html'), '', $original_author))
		{
			$no_encode = FALSE;
		}
		if ($no_encode === TRUE && strpos($mod, 'no_html') === FALSE)
		{
			$search = array('$', '"', "'", '\\', '<?');
			$replace = array('&#036;', '&quot;', '&#039;', '&#092;', '&lt;?');
			$ret = str_replace($search, $replace, $data);
		}
		else
		{
			$data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
			$data = str_replace('\\', '&#092;', $data);

			$ret = preg_replace("/&amp;#(\d*?);/", "&#\\1;", $data);
		}
		
		// XXX - php_bbcode has been deprecated. 
		if ((strpos($mod, 'no_php') !== FALSE) || !check_class($core_pref->get('php_bbcode')))
		{
			$ret = preg_replace("#\[(php)#i", "&#91;\\1", $ret);
		}

		return $ret;
	}



	/**
	 *	Check for umatched 'dangerous' HTML tags
	 *		(these can destroy page layout where users are able to post HTML)
	 * @DEPRECATED
	 *	@param string $data
	 *	@param string $tagList - if empty, uses default list of input tags. Otherwise a CSV list of tags to check (any type)
	 *
	 *	@return boolean TRUE if an unopened closing tag found
	 *					FALSE if nothing found
	 */
	function htmlAbuseFilter($data, $tagList = '')
	{
		
		if ($tagList == '')
		{
			$checkTags = array('textarea', 'input', 'td', 'tr', 'table');
		}
		else
		{
			$checkTags = explode(',', $tagList);
		}
		$tagArray = array_flip($checkTags);
		foreach ($tagArray as &$v) { $v = 0; };		// Data fields become zero; keys are tag names.
		$data = strtolower(preg_replace('#\[code\].*?\[\/code\]#i', '', $data));            // Ignore code blocks. All lower case simplifies the rest
		$matches = array();
		if (!preg_match_all('#<(\/|)([^<>]*?[^\/])>#', $data, $matches, PREG_SET_ORDER))
		{
			//echo "No tags found<br />";
			return TRUE;				// No tags found; so all OK
		}
		//print_a($matches);
		foreach ($matches as $m)
		{
			// $m[0] is the complete tag; $m[1] is '/' or empty; $m[2] is the tag and any attributes
			list ($tag) = explode(' ', $m[2], 2);
			if (!isset($tagArray[$tag])) continue;			// Not a tag of interest
			if ($m[1] == '/')
			{	// Closing tag
				if ($tagArray[$tag] == 0) 
				{
					//echo "Close before open: {$tag}<br />";
					return TRUE;		// Closing tag before we've had an opening tag
				}
				$tagArray[$tag]--;		// Obviously had at least one opening tag
			}
			else
			{	// Opening tag
				$tagArray[$tag]++;
			}
		}
		//print_a($tagArray);
		foreach ($tagArray as $t)
		{
			if ($t > 0) return TRUE;		// More opening tags than closing tags
		}
		return FALSE;						// OK now
	}




	/**
	 * @DEPRECATED XXX TODO Remove this horrible thing which adds junk to a db. 
	 *	Checks a string for potentially dangerous HTML tags, including malformed tags
	 *
	 */
	public function dataFilter($data, $mode='bbcode')
	{
					

		$ans = '';
		$vetWords = array('<applet', '<body', '<embed', '<frame', '<script','%3Cscript',
						 '<frameset', '<html', '<iframe', '<style', '<layer', '<link',
						 '<ilayer', '<meta', '<object', '<plaintext', 'javascript:',
						 'vbscript:','data:text/html');
		
		$ret = preg_split('#(\[code.*?\[/code.*?])#mis', $data, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );

		foreach ($ret as $s)
		{
			if (substr($s, 0, 5) != '[code')
			{
				$vl = array();
				$t = html_entity_decode(rawurldecode($s), ENT_QUOTES, CHARSET);
				$t = str_replace(array("\r", "\n", "\t", "\v", "\f", "\0"), '', $t);
				$t1 = strtolower($t);
				foreach ($vetWords as $vw)
				{
					if (strpos($t1, $vw) !== FALSE)
					{
						$vl[] = $vw;		// Add to list of words found
					}
					if (substr($vw, 0, 1) == '<')
					{
						$vw = '</'.substr($vw, 1);
						if (strpos($t1, $vw) !== FALSE)
						{
							$vl[] = $vw;		// Add to list of words found
						}
					}
				}
				// More checks here
				if (count($vl))
				{	// Do something
					$s = preg_replace_callback('#('.implode('|', $vl).')#mis', array($this, 'modtag'), $t);
				}
			}
			$s = preg_replace('#(?:onmouse.+?|onclick|onfocus)\s*?\=#', '[sanitised]$0[/sanitised]', $s);
			$s = preg_replace_callback('#base64([,\(])(.+?)([\)\'\"])#mis', array($this, 'proc64'), $s);
			$ans .= $s;
		}
		
		if($mode == 'link' && count($vl))
		{
			return "#sanitized";
		}
		
		return $ans;
	}


	/**
	 * Check base-64 encoded code
	 */
	private function proc64($match)
	{
		$decode = base64_decode($match[2]);
		return 'base64'.$match[1].base64_encode($this->dataFilter($decode)).$match[3];
	}


	// XXX REmove ME. 
	private function modTag($match)
	{
		$ans = '';
		if (isset($match[1]))
		{
			$chop = intval(strlen($match[1]) / 2);
			$ans = substr($match[1], 0, $chop).'##xss##'.substr($match[1], $chop);
		}
		else
		{
			$ans = '?????';
		}
		return '[sanitised]'.$ans.'[/sanitised]';

	}



	/**
	 *	Processes data as needed before its written to the DB.
	 *	Currently gives bbcodes the opportunity to do something
	 *
	 *	@param $data string - data about to be written to DB
	 *	@return string - modified data
	 */
	public function preFilter($data)
	{
		if (!is_object($this->e_bb))
		{
			require_once(e_HANDLER.'bbcode_handler.php');
			$this->e_bb = new e_bbcode;
		}
		$ret = $this->e_bb->parseBBCodes($data, USERID, 'default', 'PRE');			// $postID = logged in user here
		return $ret;
	}




	function toForm($text)
	{
		if(empty($text)) // fix - handle proper 0, Space etc values.
		{
			return $text;
		}
		$search = array('&#036;', '&quot;', '<', '>');
		$replace = array('$', '"', '&lt;', '&gt;');
		$text = str_replace($search, $replace, $text);
		if (e_WYSIWYG !== TRUE)
		{
			// fix for utf-8 issue with html_entity_decode(); ???
			$text = str_replace("&nbsp;", " ", $text);
		}
		return $text;
	}


	function post_toForm($text)
	{
		if(is_array($text))
		{
			foreach ($text as $key=>$value)
			{
				$text[$this->post_toForm($key)] = $this->post_toForm($value);
			}
			return $text;
		}
		if(MAGIC_QUOTES_GPC == TRUE)
		{
			$text = stripslashes($text);
		}
		return str_replace(array("'", '"', "<", ">"), array("&#039;", "&quot;", "&lt;", "&gt;"), $text);
	}


	function post_toHTML($text, $original_author = FALSE, $extra = '', $mod = FALSE)
	{
		$text = $this->toDB($text, FALSE, FALSE, $mod, $original_author);
		return $this->toHTML($text, TRUE, $extra);
	}


	function parseTemplate($text, $parseSCFiles = TRUE, $extraCodes = null, $eVars = null)
	{
		return e107::getScParser()->parseCodes($text, $parseSCFiles, $extraCodes, $eVars);
	}

	/**
	 * Simple parser
	 *
	 * @param string $template
	 * @param e_vars $vars
	 * @param string $replaceUnset string to be used if replace variable is not set, false - don't replace
	 * @return string parsed content
	 */
	function simpleParse($template, e_vars $vars, $replaceUnset='')
	{
		$this->replaceVars = $vars;
		$this->replaceUnset = $replaceUnset;
		return preg_replace_callback("#\{([a-zA-Z0-9_]+)\}#", array($this, 'simpleReplace'), $template);
	}

	protected function simpleReplace($tmp) 
	{
		$unset = ($this->replaceUnset !== false ? $this->replaceUnset : $tmp[0]);
		$key = $tmp[1];
		if(is_array($this->replaceVars))
		{
			return ($this->replaceVars[$key] !== null ? $this->replaceVars[$key]: $unset);	
		}		
	//	
		return ($this->replaceVars->$tmp[1] !== null ? $this->replaceVars->$tmp[1] : $unset); // Doesn't work. 
	}

	function htmlwrap($str, $width, $break = "\n", $nobreak = "a", $nobr = "pre", $utf = FALSE)
	{
		/*
		Pretty well complete rewrite to try and handle utf-8 properly.
		Breaks each utf-8 'word' every $width characters max. If possible, breaks after 'safe' characters.
		$break is the character inserted to flag the break.
		$nobreak is a list of tags within which word wrap is to be inactive
		*/

		//TODO handle htmlwrap somehow
		//return $str;

		// Don't wrap if non-numeric width
		$width = intval($width);
		// And trap stupid wrap counts
		if ($width < 6)
			return $str;

		// Transform protected element lists into arrays
		$nobreak = explode(" ", strtolower($nobreak));

		// Variable setup
		$intag = FALSE;
		$innbk = array();
		$drain = "";

		// List of characters it is "safe" to insert line-breaks at
		// It is not necessary to add < and > as they are automatically implied
		$lbrks = "/?!%)-}]\\\"':;&";

		// Is $str a UTF8 string?
		if ($utf || strtolower(CHARSET) == 'utf-8')
		{
			// 0x1680, 0x180e, 0x2000-0x200a, 0x2028, 0x205f, 0x3000 are 'non-ASCII' Unicode UCS-4 codepoints - see http://www.unicode.org/Public/UNIDATA/UnicodeData.txt
			// All convert to 3-byte utf-8 sequences:
			// 0x1680	0xe1	0x9a	0x80
			// 0x180e	0xe1	0xa0	0x8e
			// 0x2000	0xe2	0x80	0x80
			//   -
			// 0x200a	0xe2	0x80	0x8a
			// 0x2028	0xe2	0x80	0xa8
			// 0x205f	0xe2	0x81	0x9f
			// 0x3000	0xe3	0x80	0x80
			$utf8 = 'u';
			$whiteSpace = '#([\x20|\x0c]|[\xe1][\x9a][\x80]|[\xe1][\xa0][\x8e]|[\xe2][\x80][\x80-\x8a,\xa8]|[\xe2][\x81][\x9f]|[\xe3][\x80][\x80]+)#';
			// Have to explicitly enumerate the whitespace chars, and use non-utf-8 mode, otherwise regex fails on badly formed utf-8
		}
		else
		{
			$utf8 = '';
			// For non-utf-8, can use a simple match string
			$whiteSpace = '#(\s+)#';
		}


		// Start of the serious stuff - split into HTML tags and text between
		$content = preg_split('#(<.*?'.'>)#mis', $str, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
		foreach($content as $value)
		{
			if ($value[0] == "<")
			{
				// We are within an HTML tag
				// Create a lowercase copy of this tag's contents
				$lvalue = strtolower(substr($value, 1, -1));
				if ($lvalue)
				{
					// Tag of non-zero length
					// If the first character is not a / then this is an opening tag
					if ($lvalue[0] != "/")
					{
						// Collect the tag name
						preg_match("/^(\w*?)(\s|$)/", $lvalue, $t);

						// If this is a protected element, activate the associated protection flag
						if(in_array($t[1], $nobreak))
							array_unshift($innbk, $t[1]);
					}
					else
					{
						// Otherwise this is a closing tag
						// If this is a closing tag for a protected element, unset the flag
						if (in_array(substr($lvalue, 1), $nobreak))
						{
							reset($innbk);
							while (list($key, $tag) = each($innbk))
							{
								if (substr($lvalue, 1) == $tag)
								{
									unset($innbk[$key]);
									break;
								}
							}
							$innbk = array_values($innbk);
						}
					}
				}
				else
				{
					// Eliminate any empty tags altogether
					$value = '';
				}
				// Else if we're outside any tags, and with non-zero length string...
			}
			elseif ($value)
			{
				// If unprotected...
				if (!count($innbk))
				{
					// Use the ACK (006) ASCII symbol to replace all HTML entities temporarily
					$value = str_replace("\x06", "", $value);
					preg_match_all("/&([a-z\d]{2,7}|#\d{2,5});/i", $value, $ents);
					$value = preg_replace("/&([a-z\d]{2,7}|#\d{2,5});/i", "\x06", $value);
					//			echo "Found block length ".strlen($value).': '.substr($value,20).'<br />';
					// Split at spaces - note that this will fail if presented with invalid utf-8 when doing the regex whitespace search
					//			$split = preg_split('#(\s)#'.$utf8, $value, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
					$split = preg_split($whiteSpace, $value, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
					$value = '';
					foreach ($split as $sp)
					{
						//			echo "Split length ".strlen($sp).': '.substr($sp,20).'<br />';
						$loopCount = 0;
						while (strlen($sp) > $width)
						{
							// Enough characters that we may need to do something.
							$pulled = '';
							if ($utf8)
							{
								// Pull out a piece of the maximum permissible length
								if (preg_match('#^((?:[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]+){0,'.$width.'})(.{0,1}).*#s',$sp,$matches) == 0)
								{
									// Make any problems obvious for now
									$value .= '[!<b>invalid utf-8: '.$sp.'<b>!]';
									$sp = '';
								}
								elseif (empty($matches[2]))
								{
									// utf-8 length is less than specified - treat as a special case
									$value .= $sp;
									$sp = '';
								}
								else
								{
									// Need to find somewhere to break the string
									for($i = strlen($matches[1]) - 1; $i >= 0; $i--)
									{
										if(strpos($lbrks, $matches[1][$i]) !== FALSE)
											break;
									}
									if($i < 0)
									{
										// No 'special' break character found - break at the word boundary
										$pulled = $matches[1];
									}
									else
									{
										$pulled = substr($sp, 0, $i + 1);
									}
								}
								$loopCount++;
								if ($loopCount > 20)
								{
									// Make any problems obvious for now
									$value .= '[!<b>loop count exceeded: '.$sp.'</b>!]';
									$sp = '';
								}
							}
							else
							{
								for ($i = min($width, strlen($sp)); $i > 0; $i--)
								{
									// No speed advantage to defining match character
									if (strpos($lbrks, $sp[$i-1]) !== FALSE)
										break;
								}
								if ($i == 0)
								{
									// No 'special' break boundary character found - break at the word boundary
									$pulled = substr($sp, 0, $width);
								}
								else
								{
									$pulled = substr($sp, 0, $i);
								}
							}
							if ($pulled)
							{
								$value .= $pulled.$break;
								// Shorten $sp by whatever we've processed (will work even for utf-8)
								$sp = substr($sp, strlen($pulled));
							}
						}
						// Add in any residue
						$value .= $sp;
					}
					// Put captured HTML entities back into the string
					foreach ($ents[0] as $ent)
						$value = preg_replace("/\x06/", $ent, $value, 1);
				}
			}
			// Send the modified segment down the drain
			$drain .= $value;
		}
		// Return contents of the drain
		return $drain;
	}

	/**
	 * CakePHP(tm) :  Rapid Development Framework (http://www.cakephp.org)
	 * Copyright 2005-2008, Cake Software Foundation, Inc. (http://www.cakefoundation.org)
	 *
	 * Truncate a HTML string
	 *
	 * Cuts a string to the length of $length and adds the value of $ending if the text is longer than length.
	 *
	 * @param string  $text String to truncate.
	 * @param integer $length Length of returned string, including ellipsis.
	 * @param string $ending It will be used as Ending and appended to the trimmed string.
	 * @param boolean $exact If false, $text will not be cut mid-word
	 * @return string Trimmed string.
	 */
	function html_truncate($text, $length = 100, $ending = '...', $exact = true)
	{
		if($this->ustrlen(preg_replace('/<.*?>/', '', $text)) <= $length)
		{
			return $text;
		}
		$totalLength = 0;
		$openTags = array();
		$truncate = '';
		preg_match_all('/(<\/?([\w+]+)[^>]*>)?([^<>]*)/', $text, $tags, PREG_SET_ORDER);

		foreach($tags as $tag)
		{
			if(!$tag[2] || !preg_match('/img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param/si', $tag[2]))
			{
				if(preg_match('/<[\w]+[^>]*>/s', $tag[0]))
				{
					array_unshift($openTags, $tag[2]);
				}
				else if(preg_match('/<\/([\w]+)[^>]*>/s', $tag[0], $closeTag))
				{
					$pos = array_search($closeTag[1], $openTags);
					if($pos !== false)
					{
						array_splice($openTags, $pos, 1);
					}
				}
			}
			$truncate .= $tag[1];
			$contentLength = $this->ustrlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', ' ', $tag[3]));

			if($contentLength + $totalLength > $length)
			{
				$left = $length - $totalLength;
				$entitiesLength = 0;
				if(preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', $tag[3], $entities, PREG_OFFSET_CAPTURE))
				{
					foreach($entities[0] as $entity)
					{
						if($entity[1] + 1 - $entitiesLength <= $left)
						{
							$left--;
							$entitiesLength += $this->ustrlen($entity[0]);
						}
						else
						{
							break;
						}
					}
				}

				$truncate .= $this->usubstr($tag[3], 0, $left + $entitiesLength);
				break;
			}
			else
			{
				$truncate .= $tag[3];
				$totalLength += $contentLength;
			}
			if($totalLength >= $length)
			{
				break;
			}
		}
		if(!$exact)
		{
			$spacepos = $this->ustrrpos($truncate, ' ');
			if(isset($spacepos))
			{
				$bits = $this->usubstr($truncate, $spacepos);
				preg_match_all('/<\/([a-z]+)>/i', $bits, $droppedTags, PREG_SET_ORDER);
				if(!empty($droppedTags))
				{
					foreach($droppedTags as $closingTag)
					{
						if(!in_array($closingTag[1], $openTags))
						{
							array_unshift($openTags, $closingTag[1]);
						}
					}
				}
				$truncate = $this->usubstr($truncate, 0, $spacepos);
			}
		}
		$truncate .= $ending;
		foreach($openTags as $tag)
		{
			$truncate .= '</' . $tag . '>';
		}
		return $truncate;
	}

	/**
	 * Truncate a HTML string to a maximum length $len ­ append the string $more if it was truncated
	 *
	 * @param string $text String to process
	 * @param integer $len [optional] Length of characters to be truncated - default 200
	 * @param string $more [optional] String which will be added if truncation - default ' ... '
	 * @return string
	 */
	public function html_truncate_old ($text, $len = 200, $more = ' ... ')
	{
		$pos = 0;
		$curlen = 0;
		$tmp_pos = 0;
		$intag = FALSE;
		while($curlen < $len && $curlen < strlen($text))
		{
			switch($text {$pos} )
			{
				case "<":
					if($text {$pos + 1} == "/")
					{
						$closing_tag = TRUE;
					}
					$intag = TRUE;
					$tmp_pos = $pos - 1;
					$pos++;
				break;


				case ">":
					if($text {$pos - 1} == "/")
					{
						$closing_tag = TRUE;
					}
					if($closing_tag == TRUE)
					{
						$tmp_pos = 0;
						$closing_tag = FALSE;
					}
					$intag = FALSE;
					$pos++;
				break;


				case "&":
					if($text {$pos + 1} == "#")
					{
						$end = strpos(substr($text, $pos, 7), ";");
						if($end !== FALSE)
						{
							$pos += ($end + 1);
							if(!$intag)
							{
								$curlen++;
							}
						break;
						}
					}
					else
					{
						$pos++;
						if(!$intag)
						{
							$curlen++;
						}
					break;
					}
				default:
					$pos++;
					if(!$intag)
					{
						$curlen++;
					}
				break;
			}
		}
		$ret = ($tmp_pos > 0 ? substr($text, 0, $tmp_pos+1) : substr($text, 0, $pos));
		if($pos < strlen($text))
		{
			$ret = $ret.$more;
		}
		return $ret;
	}


	/**
	 * Truncate a string of text to a maximum length $len ­ append the string $more if it was truncated
	 * Uses current CHARSET ­ for utf-8, returns $len characters rather than $len bytes
	 *
	 * @param string $text ­ string to process
	 * @param integer $len ­ length of characters to be truncated
	 * @param string $more ­ string which will be added if truncation
	 * @return string
	 */
	public function text_truncate($text, $len = 200, $more = ' ... ')
	{
		// Always valid
		if($this->ustrlen($text) <= $len)
		{
			return $text;
		}
		
		$ret = $this->usubstr($text, 0, $len);

		// search for possible broken html entities
		// - if an & is in the last 8 chars, removing it and whatever follows shouldn't hurt
		// it should work for any characters encoding
		
		// FIXME - INVESTIGATE this one, switch to utf8 aware methods
		$leftAmp = $this->ustrrpos($this->usubstr($ret, -8), '&');
		if($leftAmp)
		{
			$ret = $this->usubstr($ret, 0, $this->ustrlen($ret) - 8 + $leftAmp);
		}

		return $ret.$more;
	}


	function textclean ($text, $wrap = 100)
	{
		$text = str_replace("\n\n\n", "\n\n", $text);
		$text = $this->htmlwrap($text, $wrap);
		$text = str_replace(array('<br /> ', ' <br />', ' <br /> '), '<br />', $text);
		/* we can remove any linebreaks added by htmlwrap function as any \n's will be converted later anyway */
		return $text;
	}


	// Test for text highlighting, and determine the text highlighting transformation
	// Returns TRUE if highlighting is active for this page display
	function checkHighlighting()
	{
		global $pref;

		if (!defined('e_SELF'))
		{
			// Still in startup, so can't calculate highlighting
			return FALSE;
		}

		if(!isset($this->e_highlighting))
		{
			$this->e_highlighting = FALSE;
			$shr = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "");
			if($pref['search_highlight'] && (strpos(e_SELF, 'search.php') === FALSE) && ((strpos($shr, 'q=') !== FALSE) || (strpos($shr, 'p=') !== FALSE)))
			{
				$this->e_highlighting = TRUE;
				if(!isset($this->e_query))
				{
					$query = preg_match('#(q|p)=(.*?)(&|$)#', $shr, $matches);
					$this->e_query = str_replace(array('+', '*', '"', ' '), array('', '.*?', '', '\b|\b'), trim(urldecode($matches[2])));
				}
			}
		}
		return $this->e_highlighting;
	}


	/**
	 * Converts the text (presumably retrieved from the database) for HTML output.
	 *
	 * @param string $text
	 * @param boolean $parseBB [optional]
	 * @param string $modifiers [optional] TITLE|SUMMARY|DESCRIPTION|BODY|RAW|LINKTEXT etc.
	 *		Comma-separated list, no spaces allowed
	 *		first modifier must be a CONTEXT modifier, in UPPER CASE.
	 *		subsequent modifiers are lower case - see $this->e_Modifiers for possible values
	 * @param mixed $postID [optional]
	 * @param boolean $wrap [optional]
	 * @return string
	 * @todo complete the documentation of this essential method
	 */
	public function toHTML($text, $parseBB = FALSE, $modifiers = '', $postID = '', $wrap = FALSE)
	{
		if($text == '')
		{
			return $text;
		}
		
		$pref = e107::getPref();

		global $fromadmin;

		// Set default modifiers to start
		$opts = $this->e_optDefault;


		// Now process any modifiers that are specified
		if ($modifiers)
		{
			$aMods = explode(',', $modifiers);

			// If there's a supermodifier, it must be first, and in uppercase
			$psm = trim($aMods[0]);
			if (isset($this->e_SuperMods[$psm]))
			{
				// Supermodifier found - override default values where necessary
				$opts = array_merge($opts,$this->e_SuperMods[$psm]);
				$opts['context'] = $psm;
				unset($aMods[0]);
			}

			// Now find any regular modifiers; use them to modify the context
			// (there should only be one or two out of the list of possibles)
			foreach ($aMods as $mod)
			{
				// Slight concession to varying coding styles - stripping spaces is a waste of CPU cycles!
				$mod = trim($mod);
				if (isset($this->e_Modifiers[$mod]))
				{
					// This is probably quicker than array_merge
					// - especially as usually only one or two loops
					foreach ($this->e_Modifiers[$mod] as $k => $v)
					{
						// Update our context-specific options
						$opts[$k] = $v;
					}
				}
			}
		}

		// Turn off a few things if not enabled in options
		if(!varsettrue($pref['smiley_activate']))
		{
			$opts['emotes'] = FALSE;
		}
		if(!varsettrue($pref['make_clickable']))
		{
			$opts['link_click'] = FALSE;
		}
		if(!varsettrue($pref['link_replace']))
		{
			$opts['link_replace'] = FALSE;
		}

		$fromadmin = $opts['fromadmin'];

		// Convert defines(constants) within text. eg. Lan_XXXX - must be the entire text string (i.e. not embedded)
		// The check for '::' is a workaround for a bug in the Zend Optimiser 3.3.0 and PHP 5.2.4 combination
		// - causes crashes if '::' in site name

		if($opts['defs'] && (strlen($text) < 35) && ((strpos($text, '::') === FALSE) && defined(trim($text))))
		{
			return constant(trim($text));
		}

		if ($opts['no_tags'])
		{
			$text = strip_tags($text);
		}
		
		if (MAGIC_QUOTES_GPC == TRUE) // precaution for badly saved data. 
		{
			$text = stripslashes($text);
		}


		// Make sure we have a valid count for word wrapping
		if (!$wrap && $pref['main_wordwrap'])
		{
			$wrap = $pref['main_wordwrap'];
		}
//		$text = " ".$text;


		// Now get on with the parsing
		$ret_parser = '';
		$last_bbcode = '';
		// So we can change them on each loop
		$saveOpts = $opts;
		
		
		
		if ($parseBB == FALSE)
		{
			$content = array($text);
		}
		else
		{
			// Split each text block into bits which are either within one of the 'key' bbcodes, or outside them
			// (Because we have to match end words, the 'extra' capturing subpattern gets added to output array. We strip it later)
			$content = preg_split('#(\[(table|html|php|code|scode|hide).*?\[/(?:\\2)\])#mis', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
		}


		// Use $full_text variable so its available to special bbcodes if required
		foreach ($content as $full_text)
		{
			$proc_funcs = TRUE;
			$convertNL = TRUE;

			// We may have 'captured' a bbcode word - strip it if so
			if ($last_bbcode == $full_text)
			{
				$last_bbcode = '';
				$proc_funcs = FALSE;
				$full_text = '';
			}
			else
			{
				// Set the options for this pass
				$opts = $saveOpts;

				// Have to have a good test in case a 'non-key' bbcode starts the block
				// - so pull out the bbcode parameters while we're there
				if (($parseBB !== FALSE) && preg_match('#(^\[(table|html|php|code|scode|hide)(.*?)\])(.*?)(\[/\\2\]$)#is', $full_text, $matches ))
				{
					// It's one of the 'key' bbcodes
					// Usually don't want 'normal' processing if its a 'special' bbcode
					$proc_funcs = FALSE;
					// $matches[0] - complete block from opening bracket of opening tag to closing bracket of closing tag
					// $matches[1] - complete opening tag (inclusive of brackets)
					// $matches[2] - bbcode word
					// $matches[3] - parameter, including '='
					// $matches[4] - bit between the tags (i.e. text to process)
					// $matches[5] - closing tag
					// In case we decide to load a file
					
					$bbPath 		= e_CORE.'bbcodes/';
					$bbFile 		= strtolower(str_replace('_', '', $matches[2]));
					$bbcode 		= '';
					$className 		= '';
					$full_text 		= '';
					$code_text 		= $matches[4];
					$parm 			= $matches[3] ? substr($matches[3],1) : '';
					$last_bbcode 	= $matches[2];
	
					switch ($matches[2])
					{
						case 'php' :
							// Probably run the output through the normal processing functions - but put here so the PHP code can disable if desired
							$proc_funcs = TRUE;

							// This is just the contents of the php.bb file pulled in - its short, so will be quicker
			//				$search = array("&quot;", "&#039;", "&#036;", '<br />', E_NL, "-&gt;", "&lt;br /&gt;");
			//				$replace = array('"', "'", "$", "\n", "\n", "->", "<br />");
							// Shouldn't have any parameter on this bbcode
							// Not sure whether checks are necessary now we've reorganised
			//				if (!$matches[3]) $bbcode = str_replace($search, $replace, $matches[4]);
							// Because we're bypassing most of the initial parser processing, we should be able to just reverse the effects of toDB() and execute the code
							// [SecretR] - avoid php code injections, missing php.bb will completely disable user posted php blocks
							$bbcode = file_get_contents($bbPath.$bbFile.'.bb');
							if (!$matches[3])
							{
								$code_text = html_entity_decode($matches[4], ENT_QUOTES, 'UTF-8');
							}
							break;

						case 'html' : // This overrides and deprecates html.bb
							$proc_funcs = TRUE;
							$noBreak = TRUE;
						//	$code_text = str_replace("\r\n", " ", $code_text);
							$code_text = html_entity_decode($code_text, ENT_QUOTES, CHARSET);
							$html_start = "<!-- bbcode-html-start -->"; // markers for html-to-bbcode replacement. 
							$html_end	= "<!-- bbcode-html-end -->";
							$full_text = str_replace(array("[html]","[/html]"), "",$code_text); // quick fix.. security issue?							
							$full_text =$this->replaceConstants($full_text,'abs');	
							$full_text = $html_start.$full_text.$html_end;
							$full_text = $this->parseBBTags($full_text); // strip <bbcode> tags. 
							$opts['nobreak'] = true;
							break;
						
						case 'table' : // strip <br /> from inside of <table>		
						
							$convertNL = FALSE;
						//	break;

						case 'hide' :
							$proc_funcs = TRUE;

						default :		// Most bbcodes will just execute their normal file
							// @todo should we cache these bbcodes? require_once should make class-related codes quite efficient
							if (file_exists($bbPath.'bb_'.$bbFile.'.php'))
							{	// Its a bbcode class file
								require_once($bbPath.'bb_'.$bbFile.'.php');
								$className = 'bb_'.$last_bbcode;
								$this->bbList[$last_bbcode] = new $className();
							}
							elseif(file_exists($bbPath.$bbFile.'.bb'))
							{
								$bbcode = file_get_contents($bbPath.$bbFile.'.bb');
							}
					}   // end - switch ($matches[2])

					if ($className)
					{
						$tempCode = new $className();
						$full_text = $tempCode->bbPreDisplay($matches[4], $parm);
					}
					elseif ($bbcode)
					{	// Execute the file
						$full_text = eval($bbcode);			// Require output of bbcode to be returned
						// added to remove possibility of nested bbcode exploits ...
						//   (same as in bbcode_handler - is it right that it just operates on $bbcode_return and not on $bbcode_output? - QUERY XXX-02
					}
					if(strpos($full_text, '[') !== FALSE)
					{
						$exp_search = array('eval', 'expression');
						$exp_replace = array('ev<b></b>al', 'expres<b></b>sion');
						$bbcode_return = str_replace($exp_search, $exp_replace, $full_text);
					}
				}
			}


			// Do the 'normal' processing - in principle, as previously - but think about the order.
			if ($proc_funcs && !empty($full_text)) // some more speed
			{

				// Split out and ignore any scripts and style blocks. With just two choices we can match the closing tag in the regex
				$subcon = preg_split('#((?:<s)(?:cript[^>]+>.*?</script>|tyle[^>]+>.*?</style>))#mis', $full_text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
				foreach ($subcon as $sub_blk)
				{
					if(substr($sub_blk, 0, 7) == '<script')
					{
						if($opts['scripts'])
						{
							// Strip scripts unless permitted
							$ret_parser .= $sub_blk;
						}
					}
					elseif(substr($sub_blk, 0, 6) == '<style')
					{
						// Its a style block - just pass it through unaltered - except, do we need the line break stuff? - QUERY XXX-01
						if(DB_INF_SHOW)
						{
							echo "Processing stylesheet: {$sub_blk}<br />";
						}
						$ret_parser .= $sub_blk;
					}
					else
					{
						// Do 'normal' processing on a chunk


						// Could put tag stripping in here
					
/*
						//	Line break compression - filter white space after HTML tags - among other things, ensures HTML tables display properly
						// Hopefully now achieved by other means
						if ($convertNL && !$opts['nobreak'])
						{
							$sub_blk = preg_replace("#>\s*[\r]*\n[\r]*#", ">", $sub_blk);
						}
*/

						//	Link substitution
						// Convert URL's to clickable links, unless modifiers or prefs override
						if ($opts['link_click'])
						{
							if ($opts['link_replace'])
							{
								$_ext = ($pref['links_new_window'] ? " rel=\"external\"" : "");
//								$sub_blk = preg_replace("#(^|[\s])([\w]+?://(?:[\w-%]+?)(?:\.[\w-%]+?)+.*?)(?=$|[\s()[\]<]|\.\s|\.$|,\s|,$)#is", "\\1<a href=\"\\2\" {$_ext}>".$pref['link_text']."</a>", $sub_blk);
//								$sub_blk = preg_replace("#(^|[\s])((?:www|ftp)(?:\.[\w-%]+?){2}.*?)(?=$|[\s()[\]<]|\.\s|\.$|,\s|,$)#is", "\\1<a href=\"http://\\2\" {$_ext}>".$pref['link_text']."</a>", $sub_blk);
								$sub_blk = preg_replace("#(^|[\s])([\w]+?://(?:[\w-%]+?)(?:\.[\w-%]+?)+.*?)(?=$|[\s[\]<]|\.\s|\.$|,\s|,$)#is", "\\1<a href=\"\\2\" {$_ext}>".$pref['link_text']."</a>", $sub_blk);
								$sub_blk = preg_replace("#(^|[\s])((?:www|ftp)(?:\.[\w-%]+?){2}.*?)(?=$|[\s[\]<]|\.\s|\.$|,\s|,$)#is", "\\1<a href=\"http://\\2\" {$_ext}>".$pref['link_text']."</a>", $sub_blk);
								$email_text = ($pref['email_text']) ? $this->replaceConstants($pref['email_text']) : LAN_EMAIL_SUBS;
								$sub_blk = preg_replace("#([\n ])([a-z0-9\-_.]+?)@([\w\-]+\.([\w\-\.]+\.)*[\w]+)#i", "\\1<a rel='external' href='javascript:window.location=\"mai\"+\"lto:\"+\"\\2\"+\"@\"+\"\\3\";self.close();' onmouseover='window.status=\"mai\"+\"lto:\"+\"\\2\"+\"@\"+\"\\3\"; return true;' onmouseout='window.status=\"\";return true;'>".$email_text."</a>", $sub_blk);
							}
							else
							{
								$email_text = '$1$2©$3';

//								$sub_blk = preg_replace("#(^|[\s])([\w]+?://(?:[\w-%]+?)(?:\.[\w-%]+?)+.*?)(?=$|[\s()[\]<]|\.\s|\.$|,\s|,$)#is", "\\1<a href=\"\\2\" rel=\"external\">\\2</a>", $sub_blk);
//								$sub_blk = preg_replace("#(^|[\s])((?:www|ftp)(?:\.[\w-%]+?){2}.*?)(?=$|[\s()[\]<]|\.\s|\.$|,\s|,$)#is", "\\1<a href=\"http://\\2\" rel=\"external\">\\2</a>", $sub_blk);
								$sub_blk = preg_replace("#(^|[\s])([\w]+?://(?:[\w-%]+?)(?:\.[\w-%]+?)+.*?)(?=$|[\s[\]<]|\.\s|\.$|,\s|,$)#is", "\\1<a href=\"\\2\" rel=\"external\">\\2</a>", $sub_blk);
								$sub_blk = preg_replace("#(^|[\s])((?:www|ftp)(?:\.[\w-%]+?){2}.*?)(?=$|[\s[\]<]|\.\s|\.$|,\s|,$)#is", "\\1<a href=\"http://\\2\" rel=\"external\">\\2</a>", $sub_blk);
								$sub_blk = preg_replace("#([\n ])([a-z0-9\-_.]+?)@([\w\-]+\.([\w\-\.]+\.)*[\w]+)#i", "\\1<a rel='external' href='javascript:window.location=\"mai\"+\"lto:\"+\"\\2\"+\"@\"+\"\\3\";self.close();' onmouseover='window.status=\"mai\"+\"lto:\"+\"\\2\"+\"@\"+\"\\3\"; return true;' onmouseout='window.status=\"\";return true;'>".$email_text."</a>", $sub_blk);
							}
						}


						// Convert emoticons to graphical icons, if enabled
						if ($opts['emotes'])
						{
							if (!is_object($this->e_emote))
							{
							//	require_once(e_HANDLER.'emote_filter.php');
								$this->e_emote = new e_emoteFilter;
							}
							$sub_blk = $this->e_emote->filterEmotes($sub_blk);
						}


						// Reduce newlines in all forms to a single newline character (finds '\n', '\r\n', '\n\r')
						if (!$opts['nobreak'])
						{
							if ($convertNL && substr($sub_blk,0,6) != '[html]') //XXX Quick Fix, find a cleaner way. 
							{
								// We may need to convert to <br /> later
							
								$sub_blk = preg_replace("#[\r]*\n[\r]*#", E_NL, $sub_blk);
							}
							else
							{
								// Not doing any more - its HTML so keep \n so HTML is formatted
								$sub_blk = preg_replace("#[\r]*\n[\r]*#", "\n", $sub_blk);
							}
						}


						//	Entity conversion
						// Restore entity form of quotes and such to single characters, except for text destined for tag attributes or JS.
						if($opts['value'])
						{
							// output used for attribute values.
							$sub_blk = str_replace($this->replace, $this->search, $sub_blk);
						}
						else
						{
							// output not used for attribute values.
							$sub_blk = str_replace($this->search, $this->replace, $sub_blk);
						}


						//   BBCode processing (other than the four already done, which shouldn't appear at all in the text)
						if ($parseBB !== FALSE)
						{
							if (!is_object($this->e_bb))
							{
								require_once(e_HANDLER.'bbcode_handler.php');
								$this->e_bb = new e_bbcode;
							}
							if ($parseBB === TRUE)
							{
								// 'Normal' or 'legacy' processing
								if($modifiers == "WYSIWYG")
								{
									$sub_blk = $this->e_bb->parseBBCodes($sub_blk, $postID, 'wysiwyg');	
								}
								else 
								{
									$sub_blk = $this->e_bb->parseBBCodes($sub_blk, $postID);	
								}
								
							}
							elseif ($parseBB === 'STRIP')
							{
								// Need to strip all BBCodes
								$sub_blk = $this->e_bb->parseBBCodes($sub_blk, $postID, 'default', TRUE);
							}
							else
							{
								// Need to strip just some BBCodes
								$sub_blk = $this->e_bb->parseBBCodes($sub_blk, $postID, 'default', $parseBB);
							}
						}


						// replace all {e_XXX} constants with their e107 value. modifier determines relative/absolute conversion
						// (Moved to after bbcode processing by Cameron)
						if ($opts['constants'])
						{
							$sub_blk = $this->replaceConstants($sub_blk, $opts['constants']);		// Now decodes text values
						}


						// profanity filter
						if ($pref['profanity_filter'])
						{
							if (!is_object($this->e_pf))
							{
							//	require_once(e_HANDLER."profanity_filter.php");
								$this->e_pf = new e_profanityFilter;
							}
							$sub_blk = $this->e_pf->filterProfanities($sub_blk);
						}


						//	Shortcodes
						// Optional short-code conversion
						if ($opts['parse_sc'])
						{
							$sub_blk = $this->parseTemplate($sub_blk, TRUE);
						}


						//Run any hooked in parsers
						if ($opts['hook'])
						{
							if ( varset($pref['tohtml_hook']))
							{
								//Process the older tohtml_hook pref (deprecated)
								foreach(explode(",", $pref['tohtml_hook']) as $hook)
								{
									if (!is_object($this->e_hook[$hook]))
									{
										if(is_readable(e_PLUGIN.$hook."/".$hook.".php"))
										{
											require_once(e_PLUGIN.$hook."/".$hook.".php");
											$hook_class = "e_".$hook;
											$this->e_hook[$hook] = new $hook_class;
										}

									}
									$sub_blk = $this->e_hook[$hook]->$hook($sub_blk,$opts['context']);
								}
							}

							if(isset($pref['e_tohtml_list']) && is_array($pref['e_tohtml_list']))
							{
								foreach($pref['e_tohtml_list'] as $hook)
								{
									if (!is_object($this->e_hook[$hook]))
									{
										if(is_readable(e_PLUGIN.$hook."/e_tohtml.php"))
										{
											require_once(e_PLUGIN.$hook."/e_tohtml.php");
											$hook_class = "e_tohtml_".$hook;
											$this->e_hook[$hook] = new $hook_class;
										}
									}
									$sub_blk = $this->e_hook[$hook]->to_html($sub_blk, $opts['context']);
								}
							}
						}


						// 	Word wrap
						if ($wrap && !$opts['nobreak'])
						{
							$sub_blk = $this->textclean($sub_blk, $wrap);
						}


						//	Search highlighting
						if ($opts['emotes'])			// Why??
						{
							if ($this->checkHighlighting())
							{
								$sub_blk = $this->e_highlight($sub_blk, $this->e_query);
							}
						}

						
						

						if($convertNL == true)
						{
							// Default replaces all \n with <br /> for HTML display
							$nl_replace = '<br />';
							if ($opts['nobreak'])
							{
								$nl_replace = '';
							}
							elseif ($opts['retain_nl'])
							{
								$nl_replace = "\n";
							}
							
							$sub_blk = str_replace(E_NL, $nl_replace, $sub_blk);
						}

						$ret_parser .= $sub_blk;
					}	// End of 'normal' processing for a block of text

				}		// End of 'foreach() on each block of non-script text

			}		// End of 'normal' parsing (non-script text)
			else
			{
				// Text block that needed no processing at all
				$ret_parser .= $full_text;
			}
		}

		// Quick Fix - Remove trailing <br /> on block-level elements (eg. div, pre, table, etc. )
		$srch = array();
		$repl = array();
		
		foreach($this->blockTags as $val)
		{
			$srch[] = "</".$val."><br />";	
			$repl[]	= "</".$val.">";
		}
		
		$ret_parser = str_replace($srch, $repl, $ret_parser);

		return trim($ret_parser);
	}




	function toAttribute($text)
	{
		// URLs posted without HTML access may have an &amp; in them.
		$text = str_replace('&amp;', '&', $text);
		// Xhtml compliance.
		$text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
		if(!preg_match('/&#|\'|"|\(|\)|<|>/s', $text))
		{
			$text = $this->replaceConstants($text);
			return $text;
		}
		else
		{
			return '';
		}
	}


	/**
	 * Convert text blocks which are to be embedded within JS
	 *
	 * @param string|array $stringarray
	 * @return string
	 */
	public function toJS($stringarray)
	{
		$search = array("\r\n", "\r", "<br />", "'");
		$replace = array("\\n", "", "\\n", "\'");
		$stringarray = str_replace($search, $replace, $stringarray);
		$stringarray = strip_tags($stringarray);

		$trans_tbl = get_html_translation_table(HTML_ENTITIES);
		$trans_tbl = array_flip($trans_tbl);

		return strtr($stringarray, $trans_tbl);
	}


	/**
	 * Convert Text for RSS/XML use.
	 *
	 * @param string $text
	 * @param boolean $tags [optional]
	 * @return string
	 */
	function toRss($text, $tags = FALSE)
	{
		if($tags != TRUE)
		{
			$text = $this -> toHTML($text, TRUE);
			$text = strip_tags($text);
		}

		$text = $this->toEmail($text);
		$search = array("&amp;#039;", "&amp;#036;", "&#039;", "&#036;"," & ", e_BASE, "href='request.php");
		$replace = array("'", '$', "'", '$',' &amp; ', SITEURL, "href='".SITEURL."request.php" );
		$text = str_replace($search, $replace, $text);

		if($tags == TRUE && ($text))
		{
			$text = "<![CDATA[".$text."]]>";
		}

		return $text;
	}

	//Convert specific characters back to original form, for use in storing code (or regex) values in the db.
	function toText($text)
	{
		$search = array("&amp;#039;", "&amp;#036;", "&#039;", "&#036;", "&#092;", "&amp;#092;");
		$replace = array("'", '$', "'", '$', "\\", "\\");
		$text = str_replace($search, $replace, $text);
		return $text;
	}


	/**
	 * Set the dimensions of a thumbNail (generated by thumbUrl)
	 */
	public function setThumbSize($w=null,$h=null,$crop=null)
	{
		if($w)
		{
			$this->thumbWidth = intval($w);	
		}
		
		if($h)
		{
			$this->thumbHeight = intval($h);	
		}	
		
		if($crop)
		{
			$this->thumbCrop = intval($crop);	
		}				
		
	}





	/**
	 * Set or Get the value of the thumbNail Width. 
	 * @param $width (optional)
	 */
	public function thumbWidth($width=null)
	{
		if($val !== null)
		{
			$this->thumbWidth = intval($width);
		}
		
		return $this->thumbWidth;		
	}





	/**
	 * Set or Get the value of the thumbNail height. 
	 * @param $height (optional)
	 */
	public function thumbHeight($height= null)
	{
		if($val !== null)
		{
			$this->thumbHeight = intval($height);
		}
		
		return $this->thumbHeight;	
		
	}
	
	
	
	/**
	 * Generate an auto-sized Image URL. 
	 * @param $url - path to image or leave blank for a placeholder. 
	 * @param $options  - width and height, but leaving this empty and using $this->thumbWidth() and $this->thumbHeight() is preferred. ie. {SETWIDTH: w=x&y=x}
	 * @param $raw ??
	 * @param $full
	 */
	public function thumbUrl($url=null, $options = array(), $raw = false, $full = false)
	{
		if(substr($url,0,3)=="{e_") // Fix for broken links that use {e_MEDIA} etc.
		{
			//$url = $this->replaceConstants($url,'abs');	
			// always switch to 'nice' urls when SC is used	
			$url = str_replace($this->getUrlConstants('sc'), $this->getUrlConstants('raw'), $url);	
		}
				
		if(!is_array($options))
		{
			parse_str($options, $options);
		}
		
		if(strstr($url,e_MEDIA) || strstr($url,e_SYSTEM)) // prevent disclosure of 'hashed' path. 
		{
			$raw = true; 	
		}

		if($raw) $url = $this->createConstants($url, 'mix');
		
		$baseurl = ($full ? SITEURL : e_HTTP).'thumb.php?';
        
		$thurl = 'src='.urlencode($url).'&amp;';
				
		if(vartrue($options['aw']) || vartrue($options['ah']) || $this->thumbCrop == 1)
		{
			if($this->thumbCrop == 1 && !vartrue($options['aw']) && !vartrue($options['ah'])) // Allow templates to determine dimensions. See {SETIMAGE}
			{
				$options['aw']	= $this->thumbWidth;
				$options['ah']	 = $this->thumbHeight;
			}
			
			$thurl .= 'aw='.((integer) vartrue($options['aw'], 0)).'&amp;ah='.((integer) vartrue($options['ah'], 0));
		}
		else
		{
			if(!vartrue($options['w']) && !vartrue($options['h'])) // Allow templates to determine dimensions. See {SETIMAGE}
			{
				 $options['w'] = $this->thumbWidth;
				 $options['h'] = $this->thumbHeight;
			}
			$thurl .= 'w='.((integer) vartrue($options['w'], 0)).'&amp;h='.((integer) vartrue($options['h'], 0));
		}
		
		
		if(vartrue($options['x']))//base64 encode url
		{
			$thurl = 'id='.base64_encode($thurl);
		}

	//	echo "<br /><br />".$thurl; 
		
		return $baseurl.$thurl;
	}

	/**
	 * Help for converting to more safe URLs
	 * e.g. {e_MEDIA_FILE}path/to/video.flv => e_MEDIA_FILE/path/to/video.flv
	 *
	 * @todo support for ALL URL shortcodes (replacement methods)
	 * @param string $type sc|raw|rev|all
	 * @return array
	 */
	public function getUrlConstants($type = 'sc')
	{
		// sub-folders first!
		static $array = array(
			'e_MEDIA_FILE/' 	=> '{e_MEDIA_FILE}',
			'e_MEDIA_VIDEO/' 	=> '{e_MEDIA_VIDEO}',
			'e_MEDIA_IMAGE/' 	=> '{e_MEDIA_IMAGE}',
			'e_MEDIA_ICON/' 	=> '{e_MEDIA_ICON}',
			'e_AVATAR/' 		=> '{e_AVATAR}',
			'e_AVATAR_DEFAULT/' => '{e_AVATAR_DEFAULT}',
			'e_AVATAR_UPLOAD/' => '{e_AVATAR_UPLOAD}',
			'e_WEB_JS/' 		=> '{e_WEB_JS}',
			'e_WEB_CSS/' 		=> '{e_WEB_CSS}',
			'e_WEB_IMAGE/' 		=> '{e_WEB_IMAGE}',
			'e_IMPORT/' 		=> '{e_IMPORT}',
		//	'e_WEB_PACK/' 		=> '{e_WEB_PACK}',

			'e_BASE/' 			=> '{e_BASE}',
			'e_ADMIN/' 			=> '{e_ADMIN}',
			'e_IMAGE/' 			=> '{e_IMAGE}',
			'e_THEME/' 			=> '{e_THEME}',
			'e_PLUGIN/' 		=> '{e_PLUGIN}',
			'e_HANDLER/' 		=> '{e_HANDLER}', // BC
			'e_MEDIA/' 			=> '{e_MEDIA}',
			'e_WEB/' 			=> '{e_ADMIN}',
		);

		switch ($type)
		{
			case 'sc':
				return array_values($array);
			break;

			case 'raw':
				return array_keys($array);
			break;

			case 'rev':
				return array_reverse($array, true);
			break;

			case 'all':
				return $array;
			break;
		}
		return array();
	}


	function getEmotes()
	{
		return $this->e_emote->emotes;		
	}


	/**
	 * Replace e107 path constants
	 * Note: only an ADMIN user can convert {e_ADMIN}
	 * TODO - runtime cache of search/replace arrays (object property) when $mode !== ''
	 * @param string $text
	 * @param string $mode [optional]	abs|full "full" = produce absolute URL path, e.g. http://sitename.com/e107_plugins/etc
	 * 									'abs' = produce truncated URL path, e.g. e107plugins/etc
	 * 									"" (default) = URL's get relative path e.g. ../e107_plugins/etc
	 * @param mixed $all [optional] 	if TRUE, then when $mode is "full" or TRUE, USERID is also replaced...
	 * 									when $mode is "" (default), ALL other e107 constants are replaced
	 * @return string
	 */
	public function replaceConstants($text, $mode = '', $all = FALSE)
	{

		if($mode != "")
		{
			$e107 = e107::getInstance();

			$replace_relative = array(
				$e107->getFolder('media_files'),
				$e107->getFolder('media_video'),
				$e107->getFolder('media_image'),
				$e107->getFolder('media_icon'),
				$e107->getFolder('avatars'),
				$e107->getFolder('web_js'),
				$e107->getFolder('web_css'),
				$e107->getFolder('web_image'),
				//$e107->getFolder('web_pack'),
				e_IMAGE_ABS,
				e_THEME_ABS,
				$e107->getFolder('images'),
				$e107->getFolder('plugins'),
				$e107->getFolder('files'),
				$e107->getFolder('themes'),
			//	$e107->getFolder('downloads'),
				$e107->getFolder('handlers'),
				$e107->getFolder('media'),
				$e107->getFolder('web'),
				$e107->site_theme ? $e107->getFolder('themes').$e107->site_theme.'/' : '',
				defset('THEME_ABS'),
				(ADMIN ? $e107->getFolder('admin') : ''),
				'',
				$e107->getFolder('core'),
				$e107->getFolder('system'),
			);

			switch ($mode)
			{
				case 'abs':
					$replace_absolute = array(
						e_MEDIA_FILE_ABS,
						e_MEDIA_VIDEO_ABS,
						e_MEDIA_IMAGE_ABS,
						e_MEDIA_ICON_ABS,
						e_AVATAR_ABS,
						e_JS_ABS,
						e_CSS_ABS,
						e_WEB_IMAGE_ABS,
				//		e_PACK_ABS,
						e_IMAGE_ABS,
						e_THEME_ABS,
						e_IMAGE_ABS,
						e_PLUGIN_ABS,
						e_FILE_ABS,
						e_THEME_ABS,
				//		e_DOWNLOAD_ABS, //impossible when download is done via php.
						'', // handlers - no ABS path available
						e_MEDIA_ABS,
						e_WEB_ABS,
						defset('THEME_ABS'),
						defset('THEME_ABS'),
						(ADMIN ? e_ADMIN_ABS : ''),
						$e107->server_path,
						'', // no e_CORE absolute path
						'', // no e_SYSTEM absolute path
					);
				break;

				case 'full':
					$replace_absolute = array(
						SITEURLBASE.e_MEDIA_FILE_ABS,
						SITEURLBASE.e_MEDIA_VIDEO_ABS,
						SITEURLBASE.e_MEDIA_IMAGE_ABS,
						SITEURLBASE.e_MEDIA_ICON_ABS,
						SITEURLBASE.e_AVATAR_ABS,
						SITEURLBASE.e_JS_ABS,
						SITEURLBASE.e_CSS_ABS,
						SITEURLBASE.e_WEB_IMAGE_ABS,
				//		SITEURLBASE.e_PACK_ABS,
						SITEURLBASE.e_IMAGE_ABS,
						SITEURLBASE.e_THEME_ABS,
						SITEURLBASE.e_IMAGE_ABS,
						SITEURLBASE.e_PLUGIN_ABS,
						SITEURLBASE.e_FILE_ABS, // deprecated
						SITEURLBASE.e_THEME_ABS,
						//SITEURL.$e107->getFolder('downloads'),
						'', //  handlers - no ABS path available
						SITEURLBASE.e_MEDIA_ABS,
						SITEURLBASE.e_WEB_ABS,
						defset('THEME_ABS') ? SITEURLBASE.THEME_ABS : '',
						defset('THEME_ABS') ? SITEURLBASE.THEME_ABS : '',
						(ADMIN ? SITEURLBASE.e_ADMIN_ABS : ''),
						SITEURL,
						'', // no e_CORE absolute path
						'', // no e_SYSTEM absolute path
					);
				break;
			}
			// sub-folders first!
			$search = array(
				'{e_MEDIA_FILE}',
				'{e_MEDIA_VIDEO}',
				'{e_MEDIA_IMAGE}',
				'{e_MEDIA_ICON}',
				'{e_AVATAR}',
				'{e_WEB_JS}',
				'{e_WEB_CSS}',
				'{e_WEB_IMAGE}',
		//		'{e_WEB_PACK}',
				"{e_IMAGE_ABS}",
				"{e_THEME_ABS}",
				"{e_IMAGE}",
				"{e_PLUGIN}",
				"{e_FILE}",
				"{e_THEME}",
				//,"{e_DOWNLOAD}"
				"{e_HANDLER}",
				"{e_MEDIA}",
				"{e_WEB}",
				"{THEME}",
				"{THEME_ABS}",
				"{e_ADMIN}",
				"{e_BASE}",
				"{e_CORE}",
				"{e_SYSTEM}",
			);

			/*if (ADMIN)
			{
				$replace_relative[] = $e107->getFolder('admin');
				$replace_absolute[] = SITEURL.$e107->getFolder('admin');
				$search[] = "{e_ADMIN}";
			}*/

			if ($all)
			{
				if (USER)
				{  // Can only replace with valid number for logged in users
					$replace_relative[] = USERID;
					$replace_absolute[] = USERID;
				}
				else
				{
					$replace_relative[] = '';
					$replace_absolute[] = '';
				}
				$search[] = "{USERID}";
			}

			// current THEME
			/*if(!defined('THEME'))
			{
				//if not already parsed by doReplace
				$text = str_replace(array('{THEME}', '{THEME_ABS}'), '', $text);
			}
			else
			{
				$replace_relative[] = THEME;
				$replace_absolute[] = THEME_ABS;
				$search[] = "{THEME}";
				$replace_relative[] = THEME;
				$replace_absolute[] = THEME_ABS;
				$search[] = "{THEME_ABS}";
			}*/

			$replace = ((string)$mode == "full" || (string)$mode=='abs' ) ? $replace_absolute : $replace_relative;
			return str_replace($search,$replace,$text);
		}

//		$pattern = ($all ? "#\{([A-Za-z_0-9]*)\}#s" : "#\{(e_[A-Z]*)\}#s");
		$pattern = ($all ? '#\{([A-Za-z_0-9]*)\}#s' : '#\{(e_[A-Z]*(?:_IMAGE|_VIDEO|_FILE|_CONTENT|_ICON|_AVATAR|_JS|_CSS|_PACK|_DB|_ABS){0,1})\}#s');
		$text = preg_replace_callback($pattern, array($this, 'doReplace'), $text);

		if(!defined('THEME'))
		{
			//if not already parsed by doReplace
			$text = str_replace(array('{THEME}', '{THEME_ABS}'), '', $text);
		}
		else
		{
			$srch = array('{THEME}', '{THEME_ABS}');
			$repl = array(THEME, THEME_ABS);
			$text = str_replace($srch, $repl, $text);
		}

		return $text;
	}


	function doReplace($matches)
	{
		if(defined($matches[1]) && (deftrue('ADMIN') || strpos($matches[1], 'ADMIN') === FALSE))
		{
			return constant($matches[1]);
		}
		return $matches[1];
	}

	/**
	 * Create and substitute e107 constants in passed URL
	 *
	 * @param string $url
	 * @param integer $mode 0-folders, 1-relative ('rel'), 2-absolute ('abs'), 3-full ('full') (with domain), 4-absolute & relative ('mix') (combination of 1,2,3)
	 * @return string
	 */
	public function createConstants($url, $mode = 0)
	{
		
		//FIXME - create constants for absolute paths and site URL's
		if (!is_numeric($mode))
		{
			switch ($mode)
			{
				case 'rel' : $mode = 1; break;
				case 'abs' : $mode = 2; break;
				case 'full' : $mode = 3; break;
				case 'mix' : $mode = 4; break;
				case 'nice': $mode = 5; break;
			}
		}
		$e107 = e107::getInstance();
		switch($mode)
		{
			case 0: // folder name only.
				$tmp = array(
					'{e_MEDIA_FILE}'	=> $e107->getFolder('media_files'),
					'{e_MEDIA_VIDEO}'	=> $e107->getFolder('media_videos'),
					'{e_MEDIA_IMAGE}'	=> $e107->getFolder('media_images'),
					'{e_MEDIA_ICON}'	=> $e107->getFolder('media_icons'),
					'{e_AVATAR}'		=> $e107->getFolder('avatars'),
					'{e_WEB_JS}'		=> $e107->getFolder('web_js'),
					'{e_WEB_CSS}'		=> $e107->getFolder('web_css'),
					'{e_WEB_IMAGE}'		=> $e107->getFolder('web_images'),
			//		'{e_WEB_PACK}'		=> $e107->getFolder('web_packs'),

					'{e_IMAGE}' 	=> $e107->getFolder('images'),
					'{e_PLUGIN}'	=> $e107->getFolder('plugins'),
					'{e_FILE}'		=> $e107->getFolder('files'),
					'{e_THEME}'		=> $e107->getFolder('themes'),
					'{e_DOWNLOAD}'	=> $e107->getFolder('downloads'),
					'{e_ADMIN}'		=> $e107->getFolder('admin'),
					'{e_HANDLER}'	=> $e107->getFolder('handlers'),
					'{e_MEDIA}'		=> $e107->getFolder('media'),
					'{e_WEB}'		=> $e107->getFolder('web'),
					'{e_UPLOAD}'	=> $e107->getFolder('uploads'),
					);
					
			break;


			
			case 1: // relative path only
				$tmp = array(
					'{e_MEDIA_FILE}'	=> e_MEDIA_FILE,
					'{e_MEDIA_VIDEO}'	=> e_MEDIA_VIDEO,
					'{e_MEDIA_IMAGE}'	=> e_MEDIA_IMAGE,
					'{e_MEDIA_ICON}'	=> e_MEDIA_ICON,
					'{e_AVATAR}'		=> e_AVATAR,
					'{e_IMPORT}'		=> e_IMPORT,
					'{e_WEB_JS}'		=> e_WEB_JS,
					'{e_WEB_CSS}'		=> e_WEB_CSS,
					'{e_WEB_IMAGE}'		=> e_WEB_IMAGE,
				//	'{e_WEB_PACK}'		=> e_WEB_PACK,

					'{e_IMAGE}'		=> e_IMAGE,
					'{e_PLUGIN}'	=> e_PLUGIN,
					'{e_FILE}'		=> e_FILE,
					'{e_THEME}'		=> e_THEME,
					'{e_DOWNLOAD}'	=> e_DOWNLOAD,
					'{e_ADMIN}'		=> e_ADMIN,
					'{e_HANDLER}'	=> e_HANDLER,
					'{e_MEDIA}'		=> e_MEDIA,
					'{e_WEB}'		=> e_WEB,
					'{e_UPLOAD}'	=> e_UPLOAD,
				);
			break;

			case 2: // absolute path only
				$tmp = array(
					'{e_MEDIA_FILE}'	=> e_MEDIA_FILE_ABS,
					'{e_MEDIA_VIDEO}'	=> e_MEDIA_VIDEO_ABS,
					'{e_MEDIA_IMAGE}'	=> e_MEDIA_IMAGE_ABS,
					'{e_MEDIA_ICON}'	=> e_MEDIA_ICON_ABS,
					'{e_AVATAR}'		=> e_AVATAR_ABS,
					'{e_WEB_JS}'		=> e_JS_ABS,
					'{e_WEB_CSS}'		=> e_CSS_ABS,
					'{e_WEB_IMAGE}'		=> e_WEB_IMAGE_ABS,
			//		'{e_WEB_PACK}'		=> e_PACK_ABS,

					'{e_IMAGE}'		=> e_IMAGE_ABS,
					'{e_PLUGIN}'	=> e_PLUGIN_ABS,
					'{e_FILE}'		=> e_FILE_ABS, // deprecated
					'{e_THEME}'		=> e_THEME_ABS,
					'{e_DOWNLOAD}'	=> e_HTTP.'request.php?',// FIXME - we need solution!
					'{e_ADMIN}'		=> e_ADMIN_ABS,
					//'{e_HANDLER}'	=> e_HANDLER_ABS, - no ABS path available
					'{e_MEDIA}'		=> e_MEDIA_ABS,
					'{e_WEB}'		=> e_WEB_ABS,
					'{e_BASE}'		=> e_HTTP,
				);
			break;

			case 3: // full path (e.g http://domain.com/e107_images/)
				$tmp = array(
					'{e_MEDIA_FILE}'	=> SITEURLBASE.e_MEDIA_FILE_ABS,
					'{e_MEDIA_VIDEO}'	=> SITEURLBASE.e_MEDIA_VIDEO_ABS,
					'{e_MEDIA_IMAGE}'	=> SITEURLBASE.e_MEDIA_IMAGE_ABS,
					'{e_MEDIA_ICON}'	=> SITEURLBASE.e_MEDIA_ICON_ABS,
					'{e_AVATAR}'		=> SITEURLBASE.e_AVATAR_ABS,
					'{e_WEB_JS}'		=> SITEURLBASE.e_JS_ABS,
					'{e_WEB_CSS}'		=> SITEURLBASE.e_CSS_ABS,
					'{e_WEB_IMAGE}'		=> SITEURLBASE.e_WEB_IMAGE_ABS,
			//		'{e_WEB_PACK}'		=> SITEURLBASE.e_PACK_ABS,

					'{e_IMAGE}'		=> SITEURLBASE.e_IMAGE_ABS,
					'{e_PLUGIN}'	=> SITEURLBASE.e_PLUGIN_ABS,
					'{e_FILE}'		=> SITEURLBASE.e_FILE_ABS, // deprecated
					'{e_THEME}'		=> SITEURLBASE.e_THEME_ABS,
					'{e_DOWNLOAD}'	=> SITEURLBASE.e_HTTP.'request.php?',// FIXME - we need solution!
					'{e_ADMIN}'		=> SITEURLBASE.e_ADMIN_ABS,
					//'{e_HANDLER}'	=> e_HANDLER_ABS, - no ABS path available
					'{e_MEDIA}'		=> SITEURLBASE.e_MEDIA_ABS,
					'{e_WEB}'		=> SITEURLBASE.e_WEB_ABS,
					'{e_BASE}'		=> SITEURL,
				);
			break;

			case 4: // absolute & relative paths
				$url = $this->createConstants($url, 3);
				$url = $this->createConstants($url, 2);
				$url = $this->createConstants($url, 1);
				return $url;
			break;

			case 5: // nice urls - e.g. e_MEDIA_VIDEO/mystream.flv
				$url = $this->createConstants($url, 4);
				return str_replace($this->getUrlConstants('sc'), $this->getUrlConstants('raw'), $url);
			break;

			default:
				$tmp = array();
			break;
		}

		foreach($tmp as $key=>$val)
		{
			$len = strlen($val);
			if(substr($url, 0, $len) == $val)
			{
				// replace the first instance only
				return substr_replace($url, $key, 0, $len);
			}
		}

		return $url;
	}


	//FIXME - $match not used?
	function e_highlight($text, $match)
	{
		$tags = array();
		preg_match_all('#<[^>]+>#', $text, $tags);
		$text = preg_replace('#<[^>]+>#', '<|>', $text);
		$text = preg_replace('#(\b".$match."\b)#i', '<span class="searchhighlight">\\1</span>', $text);
		foreach ($tags[0] as $tag)
		{
			$text = preg_replace('#<\|>#', $tag, $text, 1);
		}
		return $text;
	}

	
	

	/**
	 * Convert Text to a suitable format for use in emails. eg. relative links will be replaced with full links etc. 
	 * @param string $text
	 * @param boolean $posted - if the text has been posted. (uses stripslashes etc)
	 * @param string $mods - flags for text transformation. 
	 */
	public function toEmail($text, $posted = "", $mods = "parse_sc, no_make_clickable")
	{
		if ($posted === TRUE)
		{
			if (MAGIC_QUOTES_GPC)
			{
				$text = stripslashes($text);
			}
			$text = preg_replace('#\[(php)#i', '&#91;\\1', $text);
		}

		$text = (strtolower($mods) != "rawtext") ? $this->replaceConstants($text, "full") : $text;
		$text = $this->toHTML($text, TRUE, $mods);
		return $text;
	}






	/**
	 * Given an email address, returns a link including js-based obfuscation
	 */
	function emailObfuscate($email, $words = '', $subject = '')
	{
		if(strpos($email, '@') === FALSE)
		{
			return '';
		}
		if ($subject)
		{
			$subject = '?subject='.$subject;
		}
		list($name, $address) = explode('@', $email, 2);
		$reassembled = '"'.$name.'"+"@"+"'.$address.'"';
		return "<a rel='external' href='javascript:window.location=\"mai\"+\"lto:\"+".$reassembled.$subject.";self.close();' onmouseover='window.status=\"mai\"+\"lto:\"+".$reassembled."; return true;' onmouseout='window.status=\"\";return true;'>".$words.'</a>';
	}

	
	
	public function __get($name)
	{
		switch($name)
		{
			case 'e_sc':
				$ret = e107::getScParser();
			break;


			default:
				trigger_error('$e107->$'.$name.' not defined', E_USER_WARNING);
				return NULL;
			break;
		}


		$this->$name = $ret;
		return $ret;
	}
}


/**
 * New v2 Parser 
 * Start Fresh and Build on it over time to become eventual replacement to e_parse. 
 * Cameron's DOM-based parser. 
 */
class e_parser
{
    public $domObj             = null;
    private $removedList        = array();
    private $nodesToDelete      = array();
    private $nodesToConvert     = array();
    private $pathList           = array();
    private $allowedAttributes  = array(
                                    'default'   => array('id', 'style', 'class'),
                                    'img'       => array('id', 'src', 'style', 'class', 'alt', 'title', 'width', 'height'),
                                    'a'         => array('id', 'href', 'style', 'class', 'title', 'target'),
                                    'script'	=> array('type', 'src', 'language'),
                                    'iframe'	=> array('id', 'src', 'frameborder', 'class', 'width', 'height', 'style')
                                  ); 
    private $badAttrValues       = array("javascript[\s]*?:","alert\(","vbscript[\s]*?:","data:text\/html", "mhtml[\s]*?:", "data:[\s]*?image");
    private $allowedTags        = array('html', 'body','div','a','img','table','tr', 'td', 'th', 'tbody', 'thead', 'colgroup', 'b', 
                                        'i', 'pre','code', 'strong', 'u', 'em','ul','li','img','h1','h2','h3','h4','h5','h6','p',
                                        'div','pre','section','article', 'blockquote','hgroup','aside','figure','span', 'video', 'br',
                                        'small', 'caption', 'noscript'
                                   );
	private $scriptTags 		= array('script','applet','iframe'); //allowed whem $pref['post_script'] is enabled. 
	
	protected $blockTags		= array('pre','div','h1','h2','h3','h4','h5','h6','blockquote'); // element includes its own line-break. 
        
    public function __construct()
    {
		$this->init();
         /*
        $meths = get_class_methods('DomDocument');
        sort($meths);
        print_a($meths);
        */        
    }  

    /**
     * Used by e_parse to start
     */
    function init()
    {
        $this->domObj = new DOMDocument();    
    }
    
    /**
     * Set Allowed Tags. 
     * @param $array 
     */
    public function setAllowedTags($array=array())
    {
        $this->allowedTags = $array;    
    } 

 
     /**
     * Set Allowed Attributes. 
     * @param $array 
     */
    public function setAllowedAttributes($array=array())
    {
        $this->allowedAttributes = $array;    
    } 


 
	/**
	 * Add leading zeros to a number. eg. 3 might become 000003
	 * @param $num integer 
	 * @param $numDigits - total number of digits
	 * @return number with leading zeros. 
	 */
	public function leadingZeros($num,$numDigits)
	{
		return sprintf("%0".$numDigits."d",$num);
	}

	/**
	 * Generic variable translator for LAN definitions. 
	 * @param $lan - string LAN
	 * @param $vals - either a single value, which will replace '[x]' or an array with key=>value pairs. 
	 * @example $tp->lanVars("My name is [x] and I own a [y]", array('x'=>"John", 'y'=>"Cat")); 
	 */
	function lanVars($lan, $vals, $bold=false)
	{
		
		$array = (!is_array($vals)) ? array('x'=>$vals) : $vals;
		
		foreach($array as $k=>$v)
		{
			$search[] = "[".$k."]";
			$replace[] = ($bold===true) ? "<strong>".$v."</strong>" : $v;
		}
		
		return str_replace($search, $replace, $lan);
	}
    
	/**
	 * Return an Array of all specific tags found in an HTML document and their attributes.  
	 * @param $html - raw html code
	 * @param $taglist - comma separated list of tags to search or '*' for all. 
	 * @param $header - if the $html includes the html head or body tags - it should be set to true. 
	 */
	public function getTags($html, $taglist='*', $header = false)
	{
		
		if($header == false)
		{
			$html = "<html><body>".$html."</body></html>";	
		}	
		
		$doc = $this->domObj;   
		       
		$doc->preserveWhiteSpace = true;
		libxml_use_internal_errors(true);
        $doc->loadHTML($html); 	
	
		$tg = explode(",", $taglist);
		$ret = array();
		
		foreach($tg as $find)
		{
	        $tmp = $doc->getElementsByTagName($find);
			
			 
			foreach($tmp as $k=>$node)
			{
				$tag = $node->nodeName;
				$inner = $node->C14N();
				 $inner = str_replace("&#xD;","",$inner);
				
				foreach ($node->attributes as $attr)
	            {
					$name = $attr->nodeName;
	           		$value = $attr->nodeValue; 
					$ret[$tag][$k][$name] = $value; 
				}
				
				$ret[$tag][$k]['@value'] = $inner; 
				
					
			}
		}
		
		if($header == false)
		{
			unset($ret['html'],$ret['body']);
		}	
		
		
		return $ret;
	}
	
	
	
	/**
	 * Parse xxxxx.glyph file to bootstrap glyph format. 
	 * @param string $text 
	 * @param array of $parms
	 */ 
	public function toGlyph($text, $space=" ")
	{
		if(!deftrue('BOOTSTRAP'))
		{
			return false;	
		}
		
		if(is_array($space)) 
		{
			$parm = $space;
			$space = varset($parm['space'],'');
		}
		else 
		{
			$parm = array();
		}

		if(substr($text,0,2) == 'e-') 	// e107 admin icon. 
		{
			$size = (substr($text,-3) == '-32') ? 'S32' : 'S16';	
			return "<i class='".$size." ".$text."'></i>";		
		}

		// Get Glyph names. 
		$bs3 = e107::getMedia()->getGlyphs('bs3','');
		$fa4 = e107::getMedia()->getGlyphs('fa4','');
			
		list($cls,$tmp) = explode('.glyph',$text);
	//	list($type, $tmp2) = explode("-",$text,2);
		
		$removePrefix = array('glyphicon-','icon-','fa-');
		
		$id = str_replace($removePrefix, "", $cls);
		
	//	return print_r($fa4,true);
		
		if(deftrue('FONTAWESOME') &&  in_array($id ,$fa4)) // Contains FontAwesome 3 set also. 
		{
			$prefix = 'fa fa-';
			$prefix .= (vartrue($parm['size'])) ?  ' fa-'.$parm['size'] : '';	
			$tag 	= 'span';
		}
		elseif(deftrue("BOOTSTRAP")) 
		{
			if(BOOTSTRAP === 3 && in_array($id ,$bs3))
			{
				$prefix = 'glyphicon glyphicon-';
				$tag = 'span';
			}
			else
			{
				$prefix = 'icon-';	
				$tag = 'i';
			}
			
		}
		
		$text = "<".$tag." class='".$prefix.$id."'></".$tag.">" ;
		$text .= ($space !== false) ? $space : "";
		
		return $text;

		
		//$text = preg_replace('/\[(i_[\w]*)\]/',"<i class='$1'></i>", $text); 		
		// return $text;	
	}
	



	
	/**
	 * Display an icon. 
	 * @param string $icon 
	 * @example $tp->toIcon("{e_IMAGES}icons/something.png"); 
	 */
	public function toIcon($icon='',$parm = array())
	{
		
		if(!vartrue($icon))
		{
			return;
		}
		
		$ext = pathinfo($icon, PATHINFO_EXTENSION);
						
		if(!$ext || $ext == 'glyph') // Bootstrap or Font-Awesome. 
		{
			return $this->toGlyph($icon,$parm);
		}
		
		if(strpos($icon,'e_MEDIA')!==FALSE)
		{
			$path = $this->thumbUrl($icon);
		}
		elseif($icon[0] == '{')
		{
			$path = $this->replaceConstants($icon,'full');		
		}
		elseif(vartrue($parm['legacy']))
		{
			if(is_readable($parm['legacy'].$icon))
			{
				$path = $parm['legacy'].$icon;		
			}
			else
			{
				$log = e107::getAdminLog();
				$log->addDebug('Broken Icon Path: '.$icon, false)->save('IMALAN_00');
			}
			
		}
		else 
		{
			$path = $icon;
		}
		
		return "<img class='icon' src='".$path."' alt='".basename($path)."'  />";	
	}	



	
	/**
	 * Display a Video file. 
	 * @param string $file - format: id.type eg. x123dkax.youtube 
	 * @param boolean $thumbnail  - set to 'tag' to return an image thumbnail and 'src' to return the src url or 'video' for a small video thumbnail. 
	 */
	function toVideo($file, $parm=array())
	{
		list($id,$type) = explode(".",$file,2);
		
		$thumb = vartrue($parm['thumb']);
		
		if($type == 'youtube')
		{
			$video =  '<iframe width="560" height="315" src="//www.youtube.com/embed/'.$id.'" style="border:0px" allowfullscreen></iframe>';
			$thumbSrc = "https://i1.ytimg.com/vi/".$id."/0.jpg";
		
			if($thumb == 'tag')
			{
				return "<img class='img-responsive' src='".$thumbSrc."' alt='' style='max-width:".vartrue($parm['w'],'80')."px'/>";	
			}
			
			if($thumb == 'src')
			{
				return $thumbSrc;
			}
			
			if($thumb == 'video')
			{
				return '<div class="video-responsive video-thumbnail thumbnail">'.$video.'</div>';	
			}
			
			return '<div class="'.vartrue($parm['class'],'video-responsive').'">'.$video.'</div>';
		}
		
		if($type == 'mp4') //TODO FIXME 
		{
			return '
			<div class="video-responsive">
			<video width="320" height="240" controls>
			  <source src="movie.mp4" type="video/mp4">
		
			  Your browser does not support the video tag.
			</video>
			</div>';	
		}
		
		
		
		return false;
	}
	
	
	
	/**
	 * Display a Date in the browser. 
	 * Includes support for 'livestamp' (http://mattbradley.github.io/livestampjs/)
	 * @param integer $datestamp - unix timestamp
	 * @param string $format - short | long | relative 
	 * @return HTML with converted date. 
	 */
	public function toDate($datestamp = null, $format='short')
	{
		if(!is_numeric($datestamp)){ return; }

		return '<span data-livestamp="'.$datestamp.'">'.e107::getDate()->convert($datestamp, $format).'</span>';	
	}
	

		
	
	
	
	/** 
	 * Parse new <bbcode> tags into bbcode output. 
	 * @param $retainTags : when you want to replace html and retain the <bbcode> tags wrapping it. 
	 * @return html 
	 */
	function parseBBTags($text,$retainTags = false)
	{
		$bbcodes = $this->getTags($text, 'bbcode');
			
		foreach($bbcodes as $v)
		{
			foreach($v as $val)
			{
				$tag = urldecode($val['alt']);
				$repl = ($retainTags == true) ? '$1'.$tag.'$2' : $tag;
				$text = preg_replace('/(<bbcode[^>]*>).*(<\/bbcode>)/s',$repl, $text); //FIXME - handle multiple instances of bbcodes. 
			}	
		}
		return $text;
	}



	
    /**
     * Perform and render XSS Test Comparison
     */
    public function test()
    {
      //  $tp = e107::getParser();
        $sql = e107::getDb();
        
        $html = $this->getXss();
                   
        echo "<h2>Unprocessed XSS</h2>";
        // echo $html; // Remove Comment for a real mess! 
        print_a($html);
 
        echo "<h2>Standard v2 Parser</h2>";
        echo "<h3>\$tp->dataFilter()</h3>";
        // echo $tp->dataFilter($html); // Remove Comment for a real mess! 
        $sql->db_Mark_Time('------ Start Parser Test -------');
        print_a($this->dataFilter($html));   
        $sql->db_Mark_Time('tp->dataFilter');
         
        echo "<h3>\$tp->toHtml()</h3>";
        // echo $tp->dataFilter($html); // Remove Comment for a real mess! 
        print_a($this->tohtml($html));
        $sql->db_Mark_Time('tp->toHtml');     
        
        echo "<h3>\$tp->toDB()</h3>";
        // echo $tp->dataFilter($html); // Remove Comment for a real mess! 
        print_a($this->toDB($html)); 
        $sql->db_Mark_Time('tp->toDB');             
        
        
        echo "<h2>New Parser</h2>"; 
        echo "<h3>Processed</h3>";
        $cleaned = $this->cleanHtml($html);  
        print_a($cleaned);
        $sql->db_Mark_Time('new Parser');    
      //  $sql->db_Mark_Time('------ End Parser Test -------');
        echo "<h3>Processed &amp; Rendered</h3>";
        echo $cleaned;
        
        echo "<h2>New Parser - Data</h2>"; 
        echo "<h3>Converted Paths</h3>";
        print_a($this->pathList);
                   
        echo "<h3>Removed Tags and Attributes</h3>";
        print_a($this->removedList);
        
         //   print_a($p); 
    }
 
    
    /**
     * Process and clean HTML from user input. 
     * @param $html raw HTML
     * TODO Html5 tag support. 
     */
    public function cleanHtml($html='')
    {
        if(!vartrue($html)){ return; }
        
   //     $html = mb_convert_encoding($html, 'UTF-8');     
        
        if(preg_match("/<body/i",$html)!==true) // HTML Fragment
		{
       		$html = '<?xml version="1.0" encoding="utf-8"?><!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>'; 
		}
		else  // Full HTML page. 
		{
		//	$this->allowedTags[] = 'head';
		//	$this->allowedTags[] = 'body';
		//	$this->allowedTags[] = 'title';
			//$this->allowedTags[] = 'meta';
		}
         
		if(!is_object($this->domObj))
		{
			$this->init();	
		}
		
		$post_scripts = e107::getConfig()->get('post_script', e_UC_MAINADMIN); // Pref to Allow <script> tags 
		
		if(check_class($post_scripts)) 
		{
			$this->allowedTags = array_merge($this->allowedTags,$this->scriptTags);
		}
		
        // Set it up for processing. 
        $doc  = $this->domObj;   
        
        @$doc->loadHTML($html); 
        $doc->encoding = 'UTF-8'; //FIXME 
     //   $doc->resolveExternals = true;
        
    //    $tmp = $doc->getElementsByTagName('*');   
    
       	$this->nodesToConvert 	= array(); // required. 
		$this->nodesToDelete 	= array(); // required. 
		$this->removedList		= array();

		$tmp = $doc->getElementsByTagName('*');   
		
        foreach($tmp as $node)
        {
    
            $path = $node->getNodePath();
			
		//	echo "<br />Path = ".$path;
         //   $tag = strval(basename($path));
            
            $tag = preg_replace('/([a-z0-9\[\]\/]*)?\/([\w]*)(\[(\d)*\])?$/i', "$2", $path);
            if(!in_array($tag, $this->allowedTags))
            {
                  
                 if(strpos($path,'/code/') !== false || strpos($path,'/pre/') !== false) //  treat as html. 
                 {
                    $this->pathList[] = $path; 
                    $this->nodesToConvert[] =  $node->parentNode; // $node; 
                    continue;
                 }
                
                $this->removedList['tags'][] = $tag;
                $this->nodesToDelete[] = $node; 
                continue;
            }
             
            foreach ($node->attributes as $attr)
            {
                $name = $attr->nodeName;
                $value = $attr->nodeValue; 
                
                $allow = varset($this->allowedAttributes[$tag], $this->allowedAttributes['default']);

                if(!in_array($name, $allow))
                {
                     $node->removeAttribute($name);   
                     $this->removedList['attributes'][] = $name. " from <".$tag.">";
                     continue;
                }
                
                if($this->invalidAttributeVal( $value)) // Check value against whitelist. 
                {
					$node->removeAttribute($name);
                    $node->setAttribute($name, '#---sanitized---#');
					$this->removedList['sanitized'][] = $tag.'['.$name.']';    
                }       
            } 

        }
        
        // Remove some stuff. 
        foreach($this->nodesToDelete as $node)
        {
            $node->parentNode->removeChild($node);
        }  
        
        // Convert <code> and <pre> Tags to Htmlentities. 
        foreach($this->nodesToConvert as $node)  
        {
            $value = $node->C14N();

            $value = str_replace("&#xD;","",$value);
            
            if($node->nodeName == 'pre')
            {
                $value = substr($value,5);
                $end = strrpos($value,"</pre>");
                $value = substr($value,0,$end);
            }
            
            if($node->nodeName == 'code')
            {
                $value = substr($value,6);
                $end = strrpos($value,"</code>");
                $value = substr($value,0,$end);
            }
            
            $value = htmlentities(htmlentities($value)); // Needed 
            $node->nodeValue = $value;
        }  
       
       
        $cleaned = $doc->saveHTML();
        
        $cleaned = str_replace(array('<body>','</body>','<html>','</html>','<!DOCTYPE html>','<meta charset="UTF-8">','<?xml version="1.0" encoding="utf-8"?>'),'',$cleaned); // filter out tags. 
    
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES, 'UTF-8');
        
        return $cleaned;
    }
 
 
    /**
     * Check for Invalid Attribute Values
     * @param $val string 
     * @return true/false
     */   
    function invalidAttributeVal($val)
    {
    	
    	
        foreach($this->badAttrValues as $v) // global list because a bad value is bad regardless of the attribute it's in. ;-)
        {
            if(preg_match('/'.$v.'/i',$val)==true)  
            {
				$this->removedList['blacklist'][]	= "Match found for '{$v}' in '{$val}'";	
            	
                return true;    
            }   
            
        }
        
        return false;    
    }   
    
       
    
    /**
     * XSS HTML code to test against
     */
    private function getXss()
    {

$html = <<<EOF
Internationalization Test: 
ภาษาไทย <br />
日本語 <br />
简体中文 <br />
<a href='somewhere.html' src='invalidatrribute' >Test</a>
A GOOD LINK: <a href='http://mylink.php'>Some Link</a>
<a href='javascript: something' src='invalidatrribute' >Test regex</a>
<img href='invalidattribute' src='myimage.jpg' />
<frameset onload=alert(1) data-something=where>
<table background="javascript:alert(1)"><tr><td><a href="something.php" onclick="alert(1)">Hi there</a></td></tr></table>
<div>
<!--<img src="--><img src=x onerror=alert(1)//">
<comment><img src="</comment><img src=x onerror=alert(1)//">
<ul>
<li style=list-style:url() onerror=alert(1)></li> <div style=content:url(data:image/svg+xml,%3Csvg/%3E);visibility:hidden onload=alert(1)></div>
</ul>
</div>
</frameset>
<head><base href="javascript://"/></head><body><a href="/. /,alert(1)//#">XXX</a></body>
<SCRIPT FOR=document EVENT=onreadystatechange>alert(1)</SCRIPT>
<OBJECT CLASSID="clsid:333C7BC4-460F-11D0-BC04-0080C7055A83"><PARAM NAME="DataURL" VALUE="javascript:alert(1)"></OBJECT>
<b <script>alert(1)//</script>0</script></b>
<div id="div1"><input value="``onmouseover=alert(1)"></div> <div id="div2"></div><
script>document.getElementById("div2").innerHTML = document.getElementById("div1").innerHTML;</script>
Some example text<br />
<b>This is bold</b><br />
<i>This is italic</i><br />
<small>Some small text</small>
<pre>This is pre-formatted
        <script>alert('something')</script>
        <b>Bold Stuff</b>
        <pre>something</pre>
        <code>code</code>
        <b>BOLD</b>
        function myfunction()
        {
            
        }
 </pre>
<code>
        function myfunction()
        {
            
        }

<script>alert('something')</script>
</code>
<svg><![CDATA[><image xlink:href="]]><img src=xx:x onerror=alert(2)//"></svg>
<style><img src="</style><img src=x onerror=alert(1)//">
<x '="foo"><x foo='><img src=x onerror=alert(1)//'> <!-- IE 6-9 --> <! '="foo"><x foo='><img src=x onerror=alert(2)//'> <? '="foo"><x foo='><img src=x onerror=alert(3)//'>
<embed src="javascript:alert(1)"></embed> // O10.10↓, OM10.0↓, GC6↓, FF <img src="javascript:alert(2)"> <image src="javascript:alert(2)"> // IE6, O10.10↓, OM10.0↓ <script src="javascript:alert(3)"></script> // IE6, O11.01↓, OM10.1↓
<div style=width:1px;filter:glow onfilterchange=alert(1)>x</div>
<object allowscriptaccess="always" data="test.swf"></object>
[A] <? foo="><script>alert(1)</script>"> <! foo="><script>alert(1)</script>"> </ foo="><script>alert(1)</script>"> [B] <? foo="><x foo='?><script>alert(1)</script>'>"> [C] <! foo="[[[x]]"><x foo="]foo><script>alert(1)</script>"> [D] <% foo><x foo="%><script>alert(1)</script>">
<iframe src=mhtml:http://html5sec.org/test.html!xss.html></iframe> <iframe src=mhtml:http://html5sec.org/test.gif!xss.html></iframe>
<html> <body> <b>some content without two new line \n\n</b> Content-Type: multipart/related; boundary="******"<b>some content without two new line</b> --****** Content-Location: xss.html Content-Transfer-Encoding: base64 PGlmcmFtZSBuYW1lPWxvIHN0eWxlPWRpc3BsYXk6bm9uZT48L2lmcmFtZT4NCjxzY3JpcHQ+DQp1 cmw9bG9jYXRpb24uaHJlZjtkb2N1bWVudC5nZXRFbGVtZW50c0J5TmFtZSgnbG8nKVswXS5zcmM9 dXJsLnN1YnN0cmluZyg2LHVybC5pbmRleE9mKCcvJywxNSkpO3NldFRpbWVvdXQoImFsZXJ0KGZy YW1lc1snbG8nXS5kb2N1bWVudC5jb29raWUpIiwyMDAwKTsNCjwvc2NyaXB0PiAgICAg --******-- </body> </html>
<!-- IE 5-9 --> <div id=d><x xmlns="><iframe onload=alert(1)"></div> <script>d.innerHTML+='';</script> <!-- IE 10 in IE5-9 Standards mode --> <div id=d><x xmlns='"><iframe onload=alert(2)//'></div> <script>d.innerHTML+='';</script>
<img[a][b]src=x[d]onerror[c]=[e]"alert(1)">
<a href="[a]java[b]script[c]:alert(1)">XXX</a>
<img src="x` `<script>alert(1)</script>"` `>
<img src onerror /" '"= alt=alert(1)//">
<title onpropertychange=alert(1)></title><title title=></title>
<!-- IE 5-8 standards mode --> <a href=http://foo.bar/#x=`y></a><img alt="`><img src=xx:x onerror=alert(1)></a>"> <!-- IE 5-9 standards mode --> <!a foo=x=`y><img alt="`><img src=xx:x onerror=alert(2)//"> <?a foo=x=`y><img alt="`><img src=xx:x onerror=alert(3)//">
<!--[if]><script>alert(1)</script --> <!--[if<img src=x onerror=alert(2)//]> -->
<script> Blabla </script>
<script src="/\example.com\foo.js"></script> // Safari 5.0, Chrome 9, 10 <script src="\\example.com\foo.js"></script> // Safari 5.0
<object id="x" classid="clsid:CB927D12-4FF7-4a9e-A169-56E4B8A75598"></object> <object classid="clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B" onqt_error="alert(1)" style="behavior:url(#x);"><param name=postdomevents /></object>
<!-- `<img/src=xx:xx onerror=alert(1)//--!>
<xmp> <% </xmp> <img alt='%></xmp><img src=xx:x onerror=alert(1)//'> <script> x='<%' </script> %>/ alert(2) </script> XXX <style> *['<!--']{} </style> -->{} *{color:red}</style>
<a style="-o-link:'javascript:alert(1)';-o-link-source:current">X</a>
<style>p[foo=bar{}*{-o-link:'javascript:alert(1)'}{}*{-o-link-source:current}*{background:red}]{background:green};</style>
<div style="font-family:'foo[a];color:red;';">XXX</div>
<form id="test"></form><button form="test" formaction="javascript:alert(1)">X</button>
<input onfocus=write(1) autofocus>
<video poster=javascript:alert(1)//></video>
<video>somemovei.mp4</video>
<body onscroll=alert(1)><br><br><br><br><br><br>...<br><br><br><br><input autofocus>

<article id="something">Some text goes here</article>


EOF;

return $html;            
            
    }
        
    
    
    
}



class e_emotefilter {
	var $search;
	var $replace;
	var $emotes;
	 
	function e_emotefilter() /* constructor */
	{		
		$pref = e107::getPref();
		
		if(!$pref['emotepack'])	
		{	
			$pref['emotepack'] = "default";
			save_prefs();
		}
			
		$this->emotes = e107::getConfig("emote")->getPref();
		
		if(!vartrue($this->emotes))
		{
			return;
		}

		foreach($this->emotes as $key => $value)
		{
		  $value = trim($value);

		  if ($value)
		  {	// Only 'activate' emote if there's a substitution string set
			$key = preg_replace("#!(\w{3,}?)$#si", ".\\1", $key);
			// Next two probably to sort out legacy issues - may not be required any more
			$key = preg_replace("#_(\w{3})$#", ".\\1", $key);
			$key = str_replace("!", "_", $key);

			  $filename = e_IMAGE."emotes/" . $pref['emotepack'] . "/" . $key;
			  
			  $fileloc = SITEURLBASE.e_IMAGE_ABS."emotes/" . $pref['emotepack'] . "/" . $key;

			  if(file_exists($filename))
			  {
				if(strstr($value, " "))
				{
					$tmp = explode(" ", $value);
					foreach($tmp as $code)
					{
						$this->search[] = " ".$code;
						$this->search[] = "\n".$code;
						//TODO CSS class?
						$this->replace[] = " <img src='".$fileloc."' alt='' style='vertical-align:middle; border:0' /> ";
						$this->replace[] = "\n <img src='".$fileloc."' alt='' style='vertical-align:middle; border:0' /> ";
					}
					unset($tmp);
				}
				else
				{
					if($value)
					{
						$this->search[] = " ".$value;
						$this->search[] = "\n".$value;
						//TODO CSS class?
						$this->replace[] = " <img src='".$filename."' alt='' style='vertical-align:middle; border:0' /> ";
						$this->replace[] = "\n <img src='".$filename."' alt='' style='vertical-align:middle; border:0' /> ";
					}
				}
			  }
		  }
		  else
		  {
			unset($this->emotes[$key]);
		  }
		}
	}
	 
	function filterEmotes($text)
	{	 
		$text = str_replace($this->search, $this->replace, $text);
		return $text;
	}
	 
	function filterEmotesRev($text)
	{
		$text = str_replace($this->replace, $this->search, $text);
		return $text;
	}
}


class e_profanityFilter 
{
	var $profanityList;

	function e_profanityFilter() 
	{
		global $pref;

		$words = explode(",", $pref['profanity_words']);
        $word_array = array();
		foreach($words as $word) 
		{
			$word = trim($word);
			if($word != "")
			{
				$word_array[] = $word;
				if (strpos($word, '&#036;') !== FALSE)
				{
					$word_array[] = str_replace('&#036;', '\$', $word);		// Special case - '$' may be 'in clear' or as entity
				}
			}
		}
		if(count($word_array))
		{
			$this->profanityList = str_replace('#','\#',implode("\b|\b", $word_array));		// We can get entities in the string - confuse the regex delimiters
		}
		unset($words);
		return TRUE;
	}

	function filterProfanities($text) 
	{
		global $pref;
		if (!$this->profanityList) 
		{
			return $text;
		}
		if ($pref['profanity_replace']) 
		{
			return preg_replace("#\b".$this->profanityList."\b#is", $pref['profanity_replace'], $text);
		} 
		else 
		{
			return preg_replace_callback("#\b".$this->profanityList."\b#is", array($this, 'replaceProfanities'), $text);
		}
	}

	function replaceProfanities($matches) 
	{
		/*!
		@function replaceProfanities callback
		@abstract replaces vowels in profanity words with stars
		@param text string - text string to be filtered
		@result filtered text
		*/

		return preg_replace("#a|e|i|o|u#i", "*" , $matches[0]);
	}
}
