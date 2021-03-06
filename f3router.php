<?php

/*
	Copyright (c) 2009-2014 F3::Factory/Bong Cosca, All rights reserved.

	This file is part of the Fat-Free Framework (http://fatfree.sf.net).

	THE SOFTWARE AND DOCUMENTATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF
	ANY KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
	IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A PARTICULAR
	PURPOSE.

	Please see the license.txt file for more information.
*/

//! Factory class for single-instance objects
abstract class Prefab {

	/**
	*	Return class instance
	*	@return static
	**/
	static function instance() {
		if (!Registry::exists($class=get_called_class())) {
			$ref=new Reflectionclass($class);
			$args=func_get_args();
			Registry::set($class,
				$args?$ref->newinstanceargs($args):new $class);
		}
		return Registry::get($class);
	}

}

//! Base structure
class Base extends Prefab {

	//@{ Framework details
	const
		PACKAGE='Fat-Free Framework',
		VERSION='3.2.2-Release';
	//@}

	//@{ HTTP status codes (RFC 2616)
	const
		HTTP_100='Continue',
		HTTP_101='Switching Protocols',
		HTTP_200='OK',
		HTTP_201='Created',
		HTTP_202='Accepted',
		HTTP_203='Non-Authorative Information',
		HTTP_204='No Content',
		HTTP_205='Reset Content',
		HTTP_206='Partial Content',
		HTTP_300='Multiple Choices',
		HTTP_301='Moved Permanently',
		HTTP_302='Found',
		HTTP_303='See Other',
		HTTP_304='Not Modified',
		HTTP_305='Use Proxy',
		HTTP_307='Temporary Redirect',
		HTTP_400='Bad Request',
		HTTP_401='Unauthorized',
		HTTP_402='Payment Required',
		HTTP_403='Forbidden',
		HTTP_404='Not Found',
		HTTP_405='Method Not Allowed',
		HTTP_406='Not Acceptable',
		HTTP_407='Proxy Authentication Required',
		HTTP_408='Request Timeout',
		HTTP_409='Conflict',
		HTTP_410='Gone',
		HTTP_411='Length Required',
		HTTP_412='Precondition Failed',
		HTTP_413='Request Entity Too Large',
		HTTP_414='Request-URI Too Long',
		HTTP_415='Unsupported Media Type',
		HTTP_416='Requested Range Not Satisfiable',
		HTTP_417='Expectation Failed',
		HTTP_500='Internal Server Error',
		HTTP_501='Not Implemented',
		HTTP_502='Bad Gateway',
		HTTP_503='Service Unavailable',
		HTTP_504='Gateway Timeout',
		HTTP_505='HTTP Version Not Supported';
	//@}

	const
		//! Mapped PHP globals
		GLOBALS='GET|POST|COOKIE|REQUEST|SESSION|FILES|SERVER|ENV',
		//! HTTP verbs
		VERBS='GET|HEAD|POST|PUT|PATCH|DELETE|CONNECT',
		//! Default directory permissions
		MODE=0755,
		//! Syntax highlighting stylesheet
		CSS='code.css';

	//@{ HTTP request types
	const
		REQ_SYNC=1,
		REQ_AJAX=2;
	//@}

	//@{ Error messages
	const
		E_Pattern='Invalid routing pattern: %s',
		E_Named='Named route does not exist: %s',
		E_Fatal='Fatal error: %s',
		E_Open='Unable to open %s',
		E_Routes='No routes specified',
		E_Class='Invalid class %s',
		E_Method='Invalid method %s',
		E_Hive='Invalid hive key %s';
	//@}

	private
		//! Globals
		$hive,
		//! Initial settings
		$init,
		//! Language lookup sequence
		$languages,
		//! Default fallback language
		$fallback='en',
		//! NULL reference
		$null=NULL;

	/**
	*	Sync PHP global with corresponding hive key
	*	@return array
	*	@param $key string
	**/
	function sync($key) {
		return $this->hive[$key]=&$GLOBALS['_'.$key];
	}

	/**
	*	Return the parts of specified hive key
	*	@return array
	*	@param $key string
	**/
	private function cut($key) {
		return preg_split('/\[\h*[\'"]?(.+?)[\'"]?\h*\]|(->)|\./',
			$key,NULL,PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);
	}

	/**
	*	Replace tokenized URL with current route's token values
	*	@return string
	*	@param $url array|string
	**/
	function build($url) {
		if (is_array($url))
			foreach ($url as &$var) {
				$var=$this->build($var);
				unset($var);
			}
		elseif (preg_match_all('/@(\w+)/',$url,$matches,PREG_SET_ORDER))
			foreach ($matches as $match)
				if (array_key_exists($match[1],$this->hive['PARAMS']))
					$url=str_replace($match[0],
						$this->hive['PARAMS'][$match[1]],$url);
		return $url;
	}

	/**
	*	Parse string containing key-value pairs and use as routing tokens
	*	@return NULL
	*	@param $str string
	**/
	function parse($str) {
		preg_match_all('/(\w+)\h*=\h*(.+?)(?=,|$)/',
			$str,$pairs,PREG_SET_ORDER);
		foreach ($pairs as $pair)
			$this->hive['PARAMS'][$pair[1]]=trim($pair[2]);
	}

	/**
	*	Convert JS-style token to PHP expression
	*	@return string
	*	@param $str string
	**/
	function compile($str) {
		$fw=$this;
		return preg_replace_callback(
			'/(?<!\w)@(\w(?:[\w\.\[\]]|\->|::)*)/',
			function($var) use($fw) {
				return '$'.preg_replace_callback(
					'/\.(\w+)|\[((?:[^\[\]]*|(?R))*)\]/',
					function($expr) use($fw) {
						return '['.var_export(
							isset($expr[2])?
								$fw->compile($expr[2]):
								(ctype_digit($expr[1])?
									(int)$expr[1]:
									$expr[1]),TRUE).']';
					},
					$var[1]
				);
			},
			$str
		);
	}

	/**
	*	Get hive key reference/contents; Add non-existent hive keys,
	*	array elements, and object properties by default
	*	@return mixed
	*	@param $key string
	*	@param $add bool
	**/
	function &ref($key,$add=TRUE) {
		$parts=$this->cut($key);
		if ($parts[0]=='SESSION') {
			@session_start();
			$this->sync('SESSION');
		}
		elseif (!preg_match('/^\w+$/',$parts[0]))
			user_error(sprintf(self::E_Hive,$this->stringify($key)));
		if ($add)
			$var=&$this->hive;
		else
			$var=$this->hive;
		$obj=FALSE;
		foreach ($parts as $part)
			if ($part=='->')
				$obj=TRUE;
			elseif ($obj) {
				$obj=FALSE;
				if (!is_object($var))
					$var=new stdclass;
				$var=&$var->$part;
			}
			else {
				if (!is_array($var))
					$var=array();
				$var=&$var[$part];
			}
		if ($parts[0]=='ALIASES')
			$var=$this->build($var);
		return $var;
	}

	/**
	*	Return TRUE if hive key is not set
	*	(or return timestamp and TTL if cached)
	*	@return bool
	*	@param $key string
	*	@param $val mixed
	**/
	function exists($key,&$val=NULL) {
		$val=$this->ref($key,FALSE);
		return isset($val);
	}

	/**
	*	Return TRUE if hive key is empty and not cached
	*	@return bool
	*	@param $key string
	**/
	function devoid($key) {
		$val=$this->ref($key,FALSE);
		return empty($val);
	}

	/**
	*	Bind value to hive key
	*	@return mixed
	*	@param $key string
	*	@param $val mixed
	*	@param $ttl int
	**/
	function set($key,$val,$ttl=0) {
		if (preg_match('/^(GET|POST|COOKIE)\b(.+)/',$key,$expr)) {
			$this->set('REQUEST'.$expr[2],$val);
			if ($expr[1]=='COOKIE') {
				$parts=$this->cut($key);
				$jar=$this->hive['JAR'];
				if ($ttl)
					$jar['expire']=time()+$ttl;
				call_user_func_array('setcookie',array($parts[1],$val)+$jar);
			}
		}
		else switch ($key) {
			case 'ENCODING':
				$val=ini_set('default_charset',$val);
				if (extension_loaded('mbstring'))
					mb_internal_encoding($val);
				break;
			case 'FALLBACK':
				$this->fallback=$val;
				$lang=$this->language($this->hive['LANGUAGE']);
			case 'LANGUAGE':
				if (isset($lang) || $lang=$this->language($val))
					$val=$this->language($val);
				$lex=$this->lexicon($this->hive['LOCALES']);
			case 'LOCALES':
				if (isset($lex) || $lex=$this->lexicon($val))
					$this->mset($lex,$this->hive['PREFIX'],$ttl);
				break;
			case 'TZ':
				date_default_timezone_set($val);
				break;
		}
		$ref=&$this->ref($key);
		$ref=$val;
		if (preg_match('/^JAR\b/',$key))
			call_user_func_array(
				'session_set_cookie_params',$this->hive['JAR']);
		return $ref;
	}

	/**
	*	Retrieve contents of hive key
	*	@return mixed
	*	@param $key string
	*	@param $args string|array
	**/
	function get($key,$args=NULL) {
		if (is_string($val=$this->ref($key,FALSE)) && !is_null($args))
			return call_user_func_array(
				array($this,'format'),
				array_merge(array($val),is_array($args)?$args:array($args))
			);
		if (!is_null($val))
			return $val;
	}

	/**
	*	Unset hive key
	*	@return NULL
	*	@param $key string
	**/
	function clear($key) {
		// Normalize array literal
		$parts=$this->cut($key);
		if (preg_match('/^(GET|POST|COOKIE)\b(.+)/',$key,$expr)) {
			$this->clear('REQUEST'.$expr[2]);
			if ($expr[1]=='COOKIE') {
				$parts=$this->cut($key);
				$jar=$this->hive['JAR'];
				$jar['expire']=strtotime('-1 year');
				call_user_func_array('setcookie',
					array_merge(array($parts[1],''),$jar));
				unset($_COOKIE[$parts[1]]);
			}
		}
		elseif ($parts[0]=='SESSION') {
			@session_start();
			if (empty($parts[1])) {
				// End session
				session_unset();
				session_destroy();
				unset($_COOKIE[session_name()]);
				header_remove('Set-Cookie');
			}
			$this->sync('SESSION');
		}
		if (!isset($parts[1]) && array_key_exists($parts[0],$this->init))
			// Reset global to default value
			$this->hive[$parts[0]]=$this->init[$parts[0]];
		else {
			eval('unset('.$this->compile('@this->hive.'.$key).');');
			if ($parts[0]=='SESSION') {
				session_commit();
				session_start();
			}
		}
	}

	/**
	*	Multi-variable assignment using associative array
	*	@return NULL
	*	@param $vars array
	*	@param $prefix string
	*	@param $ttl int
	**/
	function mset(array $vars,$prefix='',$ttl=0) {
		foreach ($vars as $key=>$val)
			$this->set($prefix.$key,$val,$ttl);
	}

	/**
	*	Publish hive contents
	*	@return array
	**/
	function hive() {
		return $this->hive;
	}

	/**
	*	Copy contents of hive variable to another
	*	@return mixed
	*	@param $src string
	*	@param $dst string
	**/
	function copy($src,$dst) {
		$ref=&$this->ref($dst);
		return $ref=$this->ref($src,FALSE);
	}

	/**
	*	Concatenate string to hive string variable
	*	@return string
	*	@param $key string
	*	@param $val string
	**/
	function concat($key,$val) {
		$ref=&$this->ref($key);
		$ref.=$val;
		return $ref;
	}

	/**
	*	Swap keys and values of hive array variable
	*	@return array
	*	@param $key string
	*	@public
	**/
	function flip($key) {
		$ref=&$this->ref($key);
		return $ref=array_combine(array_values($ref),array_keys($ref));
	}

	/**
	*	Add element to the end of hive array variable
	*	@return mixed
	*	@param $key string
	*	@param $val mixed
	**/
	function push($key,$val) {
		$ref=&$this->ref($key);
		array_push($ref,$val);
		return $val;
	}

	/**
	*	Remove last element of hive array variable
	*	@return mixed
	*	@param $key string
	**/
	function pop($key) {
		$ref=&$this->ref($key);
		return array_pop($ref);
	}

	/**
	*	Add element to the beginning of hive array variable
	*	@return mixed
	*	@param $key string
	*	@param $val mixed
	**/
	function unshift($key,$val) {
		$ref=&$this->ref($key);
		array_unshift($ref,$val);
		return $val;
	}

	/**
	*	Remove first element of hive array variable
	*	@return mixed
	*	@param $key string
	**/
	function shift($key) {
		$ref=&$this->ref($key);
		return array_shift($ref);
	}

	/**
	*	Merge array with hive array variable
	*	@return array
	*	@param $key string
	*	@param $src string|array
	**/
	function merge($key,$src) {
		$ref=&$this->ref($key);
		return array_merge($ref,is_string($src)?$this->hive[$src]:$src);
	}

	/**
	*	Convert backslashes to slashes
	*	@return string
	*	@param $str string
	**/
	function fixslashes($str) {
		return $str?strtr($str,'\\','/'):$str;
	}

	/**
	*	Split comma-, semi-colon, or pipe-separated string
	*	@return array
	*	@param $str string
	**/
	function split($str) {
		return array_map('trim',
			preg_split('/[,;|]/',$str,0,PREG_SPLIT_NO_EMPTY));
	}

	/**
	*	Convert PHP expression/value to compressed exportable string
	*	@return string
	*	@param $arg mixed
	*	@param $stack array
	**/
	function stringify($arg,array $stack=NULL) {
		if ($stack) {
			foreach ($stack as $node)
				if ($arg===$node)
					return '*RECURSION*';
		}
		else
			$stack=array();
		switch (gettype($arg)) {
			case 'object':
				$str='';
				foreach (get_object_vars($arg) as $key=>$val)
					$str.=($str?',':'').
						var_export($key,TRUE).'=>'.
						$this->stringify($val,
							array_merge($stack,array($arg)));
				return get_class($arg).'::__set_state(array('.$str.'))';
			case 'array':
				$str='';
				$num=isset($arg[0]) &&
					ctype_digit(implode('',array_keys($arg)));
				foreach ($arg as $key=>$val)
					$str.=($str?',':'').
						($num?'':(var_export($key,TRUE).'=>')).
						$this->stringify($val,
							array_merge($stack,array($arg)));
				return 'array('.$str.')';
			default:
				return var_export($arg,TRUE);
		}
	}

	/**
	*	Flatten array values and return as CSV string
	*	@return string
	*	@param $args array
	**/
	function csv(array $args) {
		return implode(',',array_map('stripcslashes',
			array_map(array($this,'stringify'),$args)));
	}

	/**
	*	Convert snakecase string to camelcase
	*	@return string
	*	@param $str string
	**/
	function camelcase($str) {
		return preg_replace_callback(
			'/_(\w)/',
			function($match) {
				return strtoupper($match[1]);
			},
			$str
		);
	}

	/**
	*	Convert camelcase string to snakecase
	*	@return string
	*	@param $str string
	**/
	function snakecase($str) {
		return strtolower(preg_replace('/[[:upper:]]/','_\0',$str));
	}

	/**
	*	Return -1 if specified number is negative, 0 if zero,
	*	or 1 if the number is positive
	*	@return int
	*	@param $num mixed
	**/
	function sign($num) {
		return $num?($num/abs($num)):0;
	}

	/**
	*	Generate 64bit/base36 hash
	*	@return string
	*	@param $str
	**/
	function hash($str) {
		return str_pad(base_convert(
			hexdec(substr(sha1($str),-16)),10,36),11,'0',STR_PAD_LEFT);
	}

	/**
	*	Return Base64-encoded equivalent
	*	@return string
	*	@param $data string
	*	@param $mime string
	**/
	function base64($data,$mime) {
		return 'data:'.$mime.';base64,'.base64_encode($data);
	}

	/**
	*	Convert special characters to HTML entities
	*	@return string
	*	@param $str string
	**/
	function encode($str) {
		return @htmlentities($str,$this->hive['BITMASK'],
			$this->hive['ENCODING'],FALSE)?:$this->scrub($str);
	}

	/**
	*	Convert HTML entities back to characters
	*	@return string
	*	@param $str string
	**/
	function decode($str) {
		return html_entity_decode($str,$this->hive['BITMASK'],
			$this->hive['ENCODING']);
	}

	/**
	*	Attempt to clone object
	*	@return object
	*	@return $arg object
	**/
	function dupe($arg) {
		if (method_exists('ReflectionClass','iscloneable')) {
			$ref=new ReflectionClass($arg);
			if ($ref->iscloneable())
				$arg=clone($arg);
		}
		return $arg;
	}

	/**
	*	Invoke callback recursively for all data types
	*	@return mixed
	*	@param $arg mixed
	*	@param $func callback
	*	@param $stack array
	**/
	function recursive($arg,$func,$stack=NULL) {
		if ($stack) {
			foreach ($stack as $node)
				if ($arg===$node)
					return $arg;
		}
		else
			$stack=array();
		switch (gettype($arg)) {
			case 'object':
				$arg=$this->dupe($arg);
				foreach (get_object_vars($arg) as $key=>$val)
					$arg->$key=$this->recursive($val,$func,
						array_merge($stack,array($arg)));
				return $arg;
			case 'array':
				$tmp=array();
				foreach ($arg as $key=>$val)
					$tmp[$key]=$this->recursive($val,$func,
						array_merge($stack,array($arg)));
				return $tmp;
		}
		return $func($arg);
	}

	/**
	*	Remove HTML tags (except those enumerated) and non-printable
	*	characters to mitigate XSS/code injection attacks
	*	@return mixed
	*	@param $arg mixed
	*	@param $tags string
	**/
	function clean($arg,$tags=NULL) {
		$fw=$this;
		return $this->recursive($arg,
			function($val) use($fw,$tags) {
				if ($tags!='*')
					$val=trim(strip_tags($val,
						'<'.implode('><',$fw->split($tags)).'>'));
				return trim(preg_replace(
					'/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',$val));
			}
		);
	}

	/**
	*	Similar to clean(), except that variable is passed by reference
	*	@return mixed
	*	@param $var mixed
	*	@param $tags string
	**/
	function scrub(&$var,$tags=NULL) {
		return $var=$this->clean($var,$tags);
	}

	/**
	*	Return locale-aware formatted string
	*	@return string
	**/
	function format() {
		$args=func_get_args();
		$val=array_shift($args);
		// Get formatting rules
		$conv=localeconv();
		return preg_replace_callback(
			'/\{(?P<pos>\d+)\s*(?:,\s*(?P<type>\w+)\s*'.
			'(?:,\s*(?P<mod>(?:\w+(?:\s*\{.+?\}\s*,?)?)*)'.
			'(?:,\s*(?P<prop>.+?))?)?)?\}/',
			function($expr) use($args,$conv) {
				extract($expr);
				extract($conv);
				if (!array_key_exists($pos,$args))
					return $expr[0];
				if (isset($type))
					switch ($type) {
						case 'plural':
							preg_match_all('/(?<tag>\w+)'.
								'(?:\s+\{\s*(?<data>.+?)\s*\})/',
								$mod,$matches,PREG_SET_ORDER);
							$ord=array('zero','one','two');
							foreach ($matches as $match) {
								extract($match);
								if (isset($ord[$args[$pos]]) &&
									$tag==$ord[$args[$pos]] || $tag=='other')
									return str_replace('#',$args[$pos],$data);
							}
						case 'number':
							if (isset($mod))
								switch ($mod) {
									case 'integer':
										return number_format(
											$args[$pos],0,'',$thousands_sep);
									case 'currency':
										if (function_exists('money_format'))
											return money_format(
												'%n',$args[$pos]);
										$fmt=array(
											0=>'(nc)',1=>'(n c)',
											2=>'(nc)',10=>'+nc',
											11=>'+n c',12=>'+ nc',
											20=>'nc+',21=>'n c+',
											22=>'nc +',30=>'n+c',
											31=>'n +c',32=>'n+ c',
											40=>'nc+',41=>'n c+',
											42=>'nc +',100=>'(cn)',
											101=>'(c n)',102=>'(cn)',
											110=>'+cn',111=>'+c n',
											112=>'+ cn',120=>'cn+',
											121=>'c n+',122=>'cn +',
											130=>'+cn',131=>'+c n',
											132=>'+ cn',140=>'c+n',
											141=>'c+ n',142=>'c +n'
										);
										if ($args[$pos]<0) {
											$sgn=$negative_sign;
											$pre='n';
										}
										else {
											$sgn=$positive_sign;
											$pre='p';
										}
										return str_replace(
											array('+','n','c'),
											array($sgn,number_format(
												abs($args[$pos]),
												$frac_digits,
												$decimal_point,
												$thousands_sep),
												$currency_symbol),
											$fmt[(int)(
												(${$pre.'_cs_precedes'}%2).
												(${$pre.'_sign_posn'}%5).
												(${$pre.'_sep_by_space'}%3)
											)]
										);
									case 'percent':
										return number_format(
											$args[$pos]*100,0,$decimal_point,
											$thousands_sep).'%';
									case 'decimal':
										return number_format(
											$args[$pos],$prop,$decimal_point,
												$thousands_sep);
								}
							break;
						case 'date':
							if (empty($mod) || $mod=='short')
								$prop='%x';
							elseif ($mod=='long')
								$prop='%A, %d %B %Y';
							return strftime($prop,$args[$pos]);
						case 'time':
							if (empty($mod) || $mod=='short')
								$prop='%X';
							return strftime($prop,$args[$pos]);
						default:
							return $expr[0];
					}
				return $args[$pos];
			},
			$val
		);
	}

	/**
	*	Assign/auto-detect language
	*	@return string
	*	@param $code string
	**/
	function language($code) {
		$code=preg_replace('/;q=.+?(?=,|$)/','',$code);
		$code.=($code?',':'').$this->fallback;
		$this->languages=array();
		foreach (array_reverse(explode(',',$code)) as $lang) {
			if (preg_match('/^(\w{2})(?:-(\w{2}))?\b/i',$lang,$parts)) {
				// Generic language
				array_unshift($this->languages,$parts[1]);
				if (isset($parts[2])) {
					// Specific language
					$parts[0]=$parts[1].'-'.($parts[2]=strtoupper($parts[2]));
					array_unshift($this->languages,$parts[0]);
				}
			}
		}
		$this->languages=array_unique($this->languages);
		$locales=array();
		$windows=preg_match('/^win/i',PHP_OS);
		foreach ($this->languages as $locale) {
			if ($windows) {
				$parts=explode('-',$locale);
				$locale=@constant('ISO::LC_'.$parts[0]);
				if (isset($parts[1]) &&
					$country=@constant('ISO::CC_'.strtolower($parts[1])))
					$locale.='-'.$country;
			}
			$locales[]=$locale;
			$locales[]=$locale.'.'.ini_get('default_charset');
		}
		setlocale(LC_ALL,str_replace('-','_',$locales));
		return implode(',',$this->languages);
	}

	/**
	*	Transfer lexicon entries to hive
	*	@return array
	*	@param $path string
	**/
	function lexicon($path) {
		$lex=array();
		foreach ($this->languages?:array($this->fallback) as $lang) {
			if ((is_file($file=($base=$path.$lang).'.php') ||
				is_file($file=$base.'.php')) &&
				is_array($dict=require($file)))
				$lex+=$dict;
			elseif (is_file($file=$base.'.ini')) {
				preg_match_all(
					'/(?<=^|\n)(?:'.
					'(.+?)\h*=\h*'.
					'((?:\\\\\h*\r?\n|.+?)*)'.
					')(?=\r?\n|$)/',
					$this->read($file),$matches,PREG_SET_ORDER);
				if ($matches)
					foreach ($matches as $match)
						if (isset($match[1]) &&
							!array_key_exists($match[1],$lex))
							$lex[$match[1]]=trim(preg_replace(
								'/(?<!\\\\)"|\\\\\h*\r?\n/','',$match[2]));
			}
		}
		return $lex;
	}

	/**
	*	Return string representation of PHP value
	*	@return string
	*	@param $arg mixed
	**/
	function serialize($arg) {
		switch (strtolower($this->hive['SERIALIZER'])) {
			case 'igbinary':
				return igbinary_serialize($arg);
			default:
				return serialize($arg);
		}
	}

	/**
	*	Return PHP value derived from string
	*	@return string
	*	@param $arg mixed
	**/
	function unserialize($arg) {
		switch (strtolower($this->hive['SERIALIZER'])) {
			case 'igbinary':
				return igbinary_unserialize($arg);
			default:
				return unserialize($arg);
		}
	}

	/**
	*	Send HTTP/1.1 status header; Return text equivalent of status code
	*	@return string
	*	@param $code int
	**/
	function status($code) {
		$reason=@constant('self::HTTP_'.$code);
		if (PHP_SAPI!='cli')
			header('HTTP/1.1 '.$code.' '.$reason);
		return $reason;
	}

	/**
	*	Send cache metadata to HTTP client
	*	@return NULL
	*	@param $secs int
	**/
	function expire($secs=0) {
		if (PHP_SAPI!='cli') {
			header('X-Content-Type-Options: nosniff');
			header('X-Frame-Options: '.$this->hive['XFRAME']);
			header('X-Powered-By: '.$this->hive['PACKAGE']);
			header('X-XSS-Protection: 1; mode=block');
			if ($secs) {
				$time=microtime(TRUE);
				header_remove('Pragma');
				header('Expires: '.gmdate('r',$time+$secs));
				header('Cache-Control: max-age='.$secs);
				header('Last-Modified: '.gmdate('r'));
				$headers=$this->hive['HEADERS'];
				if (isset($headers['If-Modified-Since']) &&
					strtotime($headers['If-Modified-Since'])+$secs>$time) {
					$this->status(304);
					die;
				}
			}
			else
				header('Cache-Control: no-cache, no-store, must-revalidate');
		}
	}

	/**
	*	Log error; Execute ONERROR handler if defined, else display
	*	default error page (HTML for synchronous requests, JSON string
	*	for AJAX requests)
	*	@return NULL
	*	@param $code int
	*	@param $text string
	*	@param $trace array
	**/
	function error($code,$text='',array $trace=NULL) {
		$prior=$this->hive['ERROR'];
		$header=$this->status($code);
		$req=$this->hive['VERB'].' '.$this->hive['PATH'];
		if (!$text)
			$text='HTTP '.$code.' ('.$req.')';
		error_log($text);
		if (!$trace)
			$trace=array_slice(debug_backtrace(FALSE),1);
		$debug=$this->hive['DEBUG'];
		$trace=array_filter(
			$trace,
			function($frame) use($debug) {
				return $debug && isset($frame['file']) &&
					($frame['file']!=__FILE__ || $debug>1) &&
					(empty($frame['function']) ||
					!preg_match('/^(?:(?:trigger|user)_error|'.
						'__call|call_user_func)/',$frame['function']));
			}
		);
		$highlight=PHP_SAPI!='cli' &&
			$this->hive['HIGHLIGHT'] && is_file($css=__DIR__.'/'.self::CSS);
		$out='';
		$eol="\n";
		// Analyze stack trace
		foreach ($trace as $frame) {
			$line='';
			if (isset($frame['class']))
				$line.=$frame['class'].$frame['type'];
			if (isset($frame['function']))
				$line.=$frame['function'].'('.
					($debug>2 && isset($frame['args'])?
						$this->csv($frame['args']):'').')';
			$src=$this->fixslashes(str_replace($_SERVER['DOCUMENT_ROOT'].
				'/','',$frame['file'])).':'.$frame['line'].' ';
			error_log('- '.$src.$line);
			$out.='• '.($highlight?
				($this->highlight($src).' '.$this->highlight($line)):
				($src.$line)).$eol;
		}
		$this->hive['ERROR']=array(
			'status'=>$header,
			'code'=>$code,
			'text'=>$text,
			'trace'=>$trace
		);
		$handler=$this->hive['ONERROR'];
		$this->hive['ONERROR']=NULL;
		if ((!$handler ||
			$this->call($handler,$this,'beforeroute,afterroute')===FALSE) &&
			!$prior && PHP_SAPI!='cli' && !$this->hive['QUIET'])
			echo $this->hive['AJAX']?
				json_encode($this->hive['ERROR']):
				('<!DOCTYPE html>'.$eol.
				'<html>'.$eol.
				'<head>'.
					'<title>'.$code.' '.$header.'</title>'.
					($highlight?
						('<style>'.$this->read($css).'</style>'):'').
				'</head>'.$eol.
				'<body>'.$eol.
					'<h1>'.$header.'</h1>'.$eol.
					'<p>'.$this->encode($text?:$req).'</p>'.$eol.
					($debug?('<pre>'.$out.'</pre>'.$eol):'').
				'</body>'.$eol.
				'</html>');
		if ($this->hive['HALT'])
			die;
	}

	/**
	*	Mock HTTP request
	*	@return NULL
	*	@param $pattern string
	*	@param $args array
	*	@param $headers array
	*	@param $body string
	**/
	function mock($pattern,array $args=NULL,array $headers=NULL,$body=NULL) {
		$types=array('sync','ajax');
		preg_match('/([\|\w]+)\h+(?:@(\w+)(?:(\(.+?)\))*|([^\h]+))'.
			'(?:\h+\[('.implode('|',$types).')\])?/',$pattern,$parts);
		$verb=strtoupper($parts[1]);
		if ($parts[2]) {
			if (empty($this->hive['ALIASES'][$parts[2]]))
				user_error(sprintf(self::E_Named,$parts[2]));
			$parts[4]=$this->hive['ALIASES'][$parts[2]];
			if (isset($parts[3]))
				$this->parse($parts[3]);
			$parts[4]=$this->build($parts[4]);
		}
		if (empty($parts[4]))
			user_error(sprintf(self::E_Pattern,$pattern));
		$url=parse_url($parts[4]);
		$query='';
		if ($args)
			$query.=http_build_query($args);
		$query.=isset($url['query'])?(($query?'&':'').$url['query']):'';
		if ($query && preg_match('/GET|POST/',$verb)) {
			parse_str($query,$GLOBALS['_'.$verb]);
			parse_str($query,$GLOBALS['_REQUEST']);
		}
		foreach ($headers?:array() as $key=>$val)
			$_SERVER['HTTP_'.strtr(strtoupper($key),'-','_')]=$val;
		$this->hive['VERB']=$verb;
		$this->hive['URI']=$this->hive['BASE'].$url['path'];
		$this->hive['AJAX']=isset($parts[5]) &&
			preg_match('/ajax/i',$parts[5]);
		if (preg_match('/GET|HEAD/',$verb) && $query)
			$this->hive['URI'].='?'.$query;
		else
			$this->hive['BODY']=$body?:$query;
		$this->run();
	}

	/**
	*	Bind handler to route pattern
	*	@return NULL
	*	@param $pattern string|array
	*	@param $handler callback
	*	@param $ttl int
	*	@param $kbps int
	**/
	function route($pattern,$handler,$ttl=0,$kbps=0) {
		$types=array('sync','ajax');
		if (is_array($pattern)) {
			foreach ($pattern as $item)
				$this->route($item,$handler,$ttl,$kbps);
			return;
		}
		preg_match('/([\|\w]+)\h+(?:(?:@(\w+)\h*:\h*)?([^\h]+)|@(\w+))'.
			'(?:\h+\[('.implode('|',$types).')\])?/',$pattern,$parts);
		if ($parts[2])
			$this->hive['ALIASES'][$parts[2]]=$parts[3];
		elseif (!empty($parts[4])) {
			if (empty($this->hive['ALIASES'][$parts[4]]))
				user_error(sprintf(self::E_Named,$parts[4]));
			$parts[3]=$this->hive['ALIASES'][$parts[4]];
		}
		if (empty($parts[3]))
			user_error(sprintf(self::E_Pattern,$pattern));
		$type=empty($parts[5])?
			self::REQ_SYNC|self::REQ_AJAX:
			constant('self::REQ_'.strtoupper($parts[5]));
		foreach ($this->split($parts[1]) as $verb) {
			if (!preg_match('/'.self::VERBS.'/',$verb))
				$this->error(501,$verb.' '.$this->hive['URI']);
			$this->hive['ROUTES'][str_replace('@',"\x00".'@',$parts[3])]
				[$type][strtoupper($verb)]=array($handler,$ttl,$kbps);
		}
	}

	/**
	*	Reroute to specified URI
	*	@return NULL
	*	@param $url string
	*	@param $permanent bool
	**/
	function reroute($url,$permanent=FALSE) {
		if (PHP_SAPI!='cli') {
			if (preg_match('/^(?:@(\w+)(?:(\(.+?)\))*|https?:\/\/)/',
				$url,$parts)) {
				if (isset($parts[1])) {
					if (empty($this->hive['ALIASES'][$parts[1]]))
						user_error(sprintf(self::E_Named,$parts[1]));
					$url=$this->hive['BASE'].
						$this->hive['ALIASES'][$parts[1]];
					if (isset($parts[2]))
						$this->parse($parts[2]);
					$url=$this->build($url);
				}
			}
			else
				$url=$this->hive['BASE'].$url;
			header('Location: '.$url);
			$this->status($permanent?301:302);
			die;
		}
		$this->mock('GET '.$url);
	}

	/**
	*	Provide ReST interface by mapping HTTP verb to class method
	*	@return NULL
	*	@param $url string
	*	@param $class string
	*	@param $ttl int
	*	@param $kbps int
	**/
	function map($url,$class,$ttl=0,$kbps=0) {
		if (is_array($url)) {
			foreach ($url as $item)
				$this->map($item,$class,$ttl,$kbps);
			return;
		}
		$fluid=preg_match('/@\w+/',$class);
		foreach (explode('|',self::VERBS) as $method)
			if ($fluid ||
				method_exists($class,$method) ||
				method_exists($class,'__call'))
				$this->route($method.' '.
					$url,$class.'->'.strtolower($method),$ttl,$kbps);
	}

	/**
	*	Return TRUE if IPv4 address exists in DNSBL
	*	@return bool
	*	@param $ip string
	**/
	function blacklisted($ip) {
		if ($this->hive['DNSBL'] &&
			!in_array($ip,
				is_array($this->hive['EXEMPT'])?
					$this->hive['EXEMPT']:
					$this->split($this->hive['EXEMPT']))) {
			// Reverse IPv4 dotted quad
			$rev=implode('.',array_reverse(explode('.',$ip)));
			foreach (is_array($this->hive['DNSBL'])?
				$this->hive['DNSBL']:
				$this->split($this->hive['DNSBL']) as $server)
				// DNSBL lookup
				if (checkdnsrr($rev.'.'.$server,'A'))
					return TRUE;
		}
		return FALSE;
	}

	/**
	*	Match routes against incoming URI
	*	@return NULL
	**/
	function run() {
		if ($this->blacklisted($this->hive['IP']))
			// Spammer detected
			$this->error(403);
		if (!$this->hive['ROUTES'])
			// No routes defined
			user_error(self::E_Routes);
		// Match specific routes first
		krsort($this->hive['ROUTES']);
		// Convert to BASE-relative URL
		$req=preg_replace(
			'/^'.preg_quote($this->hive['BASE'],'/').'(\/.*|$)/','\1',
			$this->hive['URI']
		);
		$allowed=array();
		$case=$this->hive['CASELESS']?'i':'';
		foreach ($this->hive['ROUTES'] as $url=>$routes) {
			$url=str_replace("\x00".'@','@',$url);
			if (!preg_match('/^'.
				preg_replace('/@(\w+\b)/','(?P<\1>[^\/\?]+)',
				str_replace('\*','(.*)',preg_quote($url,'/'))).
				'\/?(?:\?.*)?$/'.$case.'um',$req,$args))
				continue;
			$route=NULL;
			if (isset($routes[$this->hive['AJAX']+1]))
				$route=$routes[$this->hive['AJAX']+1];
			elseif (isset($routes[self::REQ_SYNC|self::REQ_AJAX]))
				$route=$routes[self::REQ_SYNC|self::REQ_AJAX];
			if (!$route)
				continue;
			if ($this->hive['VERB']!='OPTIONS' &&
				isset($route[$this->hive['VERB']])) {
				$parts=parse_url($req);
				if ($this->hive['VERB']=='GET' &&
					preg_match('/.+\/$/',$parts['path']))
					$this->reroute(substr($parts['path'],0,-1).
						(isset($parts['query'])?('?'.$parts['query']):''));
				list($handler,$ttl,$kbps)=$route[$this->hive['VERB']];
				if (is_bool(strpos($url,'/*')))
					foreach (array_keys($args) as $key)
						if (is_numeric($key) && $key)
							unset($args[$key]);
				if (is_string($handler)) {
					// Replace route pattern tokens in handler if any
					$handler=preg_replace_callback('/@(\w+\b)/',
						function($id) use($args) {
							return isset($args[$id[1]])?$args[$id[1]]:$id[0];
						},
						$handler
					);
					if (preg_match('/(.+)\h*(?:->|::)/',$handler,$match) &&
						!class_exists($match[1]))
						$this->error(404);
				}
				// Capture values of route pattern tokens
				$this->hive['PARAMS']=$args=array_map('urldecode',$args);
				// Save matching route
				$this->hive['PATTERN']=$url;
				// Process request
				$body='';
				$now=microtime(TRUE);
				if (isset($ttl)) {
					$this->expire($ttl);
				}
				else
					$this->expire(0);
				if (!strlen($body)) {
					if (!$this->hive['RAW'])
						$this->hive['BODY']=file_get_contents('php://input');
					ob_start();
					// Call route handler
					$this->call($handler,array($this,$args),
						'beforeroute,afterroute');
				}
				$this->hive['RESPONSE']=$body;
				if (!$this->hive['QUIET']) {
					if ($kbps) {
						$ctr=0;
						foreach (str_split($body,1024) as $part) {
							// Throttle output
							$ctr++;
							if ($ctr/$kbps>($elapsed=microtime(TRUE)-$now) &&
								!connection_aborted())
								usleep(1e6*($ctr/$kbps-$elapsed));
							echo $part;
						}
					}
					else
						echo $body;
				}
				return;
			}
			$allowed=array_keys($route);
			break;
		}
		if (!$allowed)
			// URL doesn't match any route
			$this->error(404);
		elseif (PHP_SAPI!='cli') {
			// Unhandled HTTP method
			header('Allow: '.implode(',',$allowed));
			if ($this->hive['VERB']!='OPTIONS')
				$this->error(405);
		}
	}

	/**
	*	Execute callback/hooks (supports 'class->method' format)
	*	@return mixed|FALSE
	*	@param $func callback
	*	@param $args mixed
	*	@param $hooks string
	**/
	function call($func,$args=NULL,$hooks='') {
		if (!is_array($args))
			$args=array($args);
		// Execute function; abort if callback/hook returns FALSE
		if (is_string($func) &&
			preg_match('/(.+)\h*(->|::)\h*(.+)/s',$func,$parts)) {
			// Convert string to executable PHP callback
			if (!class_exists($parts[1]))
				user_error(sprintf(self::E_Class,
					is_string($func)?$parts[1]:$this->stringify()));
			if ($parts[2]=='->')
				$parts[1]=is_subclass_of($parts[1],'Prefab')?
					call_user_func($parts[1].'::instance'):
					new $parts[1]($this);
			$func=array($parts[1],$parts[3]);
		}
		if (!is_callable($func))
			// No route handler
			user_error(sprintf(self::E_Method,
				is_string($func)?$func:$this->stringify($func)));
		$obj=FALSE;
		if (is_array($func)) {
			$hooks=$this->split($hooks);
			$obj=TRUE;
		}
		// Execute pre-route hook if any
		if ($obj && $hooks && in_array($hook='beforeroute',$hooks) &&
			method_exists($func[0],$hook) &&
			call_user_func_array(array($func[0],$hook),$args)===FALSE)
			return FALSE;
		// Execute callback
		$out=call_user_func_array($func,$args?:array());
		if ($out===FALSE)
			return FALSE;
		// Execute post-route hook if any
		if ($obj && $hooks && in_array($hook='afterroute',$hooks) &&
			method_exists($func[0],$hook) &&
			call_user_func_array(array($func[0],$hook),$args)===FALSE)
			return FALSE;
		return $out;
	}

	/**
	*	Execute specified callbacks in succession; Apply same arguments
	*	to all callbacks
	*	@return array
	*	@param $funcs array|string
	*	@param $args mixed
	**/
	function chain($funcs,$args=NULL) {
		$out=array();
		foreach (is_array($funcs)?$funcs:$this->split($funcs) as $func)
			$out[]=$this->call($func,$args);
		return $out;
	}

	/**
	*	Execute specified callbacks in succession; Relay result of
	*	previous callback as argument to the next callback
	*	@return array
	*	@param $funcs array|string
	*	@param $args mixed
	**/
	function relay($funcs,$args=NULL) {
		foreach (is_array($funcs)?$funcs:$this->split($funcs) as $func)
			$args=array($this->call($func,$args));
		return array_shift($args);
	}

	/*
	*	Configure framework according to .ini-style file settings
	*	@return NULL
	*	@param $file string
	function config($file) {
		preg_match_all(
			'/(?<=^|\n)(?:'.
				'\[(?<section>.+?)\]|'.
				'(?<lval>[^\h\r\n;].+?)\h*=\h*'.
				'(?<rval>(?:\\\\\h*\r?\n|.+?)*)'.
			')(?=\r?\n|$)/',
			$this->read($file),$matches,PREG_SET_ORDER);
		if ($matches) {
			$sec='globals';
			foreach ($matches as $match) {
				if ($match['section'])
					$sec=$match['section'];
				elseif (in_array($sec,array('routes','maps'))) {
					call_user_func_array(
						array($this,rtrim($sec,'s')),
						array_merge(array($match['lval']),
							str_getcsv($match['rval'])));
				}
				else {
					$args=array_map(
						function($val) {
							if (is_numeric($val))
								return $val+0;
							$val=ltrim($val);
							if (preg_match('/^\w+$/i',$val) && defined($val))
								return constant($val);
							return preg_replace('/\\\\\h*(\r?\n)/','\1',$val);
						},
						// Mark quoted strings with 0x00 whitespace
						str_getcsv(preg_replace('/(?<!\\\\)(")(.*?)\1/',
							"\\1\x00\\2\\1",$match['rval']))
					);
					call_user_func_array(array($this,'set'),
						array_merge(
							array($match['lval']),
							count($args)>1?array($args):$args));
				}
			}
		}
	}

	**/

	/**
	*	Create mutex, invoke callback then drop ownership when done
	*	@return mixed
	*	@param $id string
	*	@param $func callback
	*	@param $args mixed
	**/
	function mutex($id,$func,$args=NULL) {
		if (!is_dir($tmp=$this->hive['TEMP']))
			mkdir($tmp,self::MODE,TRUE);
		// Use filesystem lock
		if (is_file($lock=$tmp.
			$this->hash($this->hive['ROOT'].$this->hive['BASE']).'.'.
			$this->hash($id).'.lock') &&
			filemtime($lock)+ini_get('max_execution_time')<microtime(TRUE))
			// Stale lock
			@unlink($lock);
		while (!($handle=@fopen($lock,'x')) && !connection_aborted())
			usleep(mt_rand(0,100));
		$out=$this->call($func,$args);
		fclose($handle);
		@unlink($lock);
		return $out;
	}

	/**
	*	Return path relative to the base directory
	*	@return string
	*	@param $url string
	**/
	function rel($url) {
		return preg_replace('/(?:https?:\/\/)?'.
			preg_quote($this->hive['BASE'],'/').'/','',rtrim($url,'/'));
	}

	/**
	*	Namespace-aware class autoloader
	*	@return mixed
	*	@param $class string
	**/
	protected function autoload($class) {
		$class=$this->fixslashes(ltrim($class,'\\'));
		foreach ($this->split($this->hive['PLUGINS'].';'.
			$this->hive['AUTOLOAD']) as $auto)
			if (is_file($file=$auto.$class.'.php') ||
				is_file($file=$auto.strtolower($class).'.php') ||
				is_file($file=strtolower($auto.$class).'.php'))
				return require($file);
	}

	/**
	*	Execute framework/application shutdown sequence
	*	@return NULL
	*	@param $cwd string
	**/
	function unload($cwd) {
		chdir($cwd);
		if (!$error=error_get_last())
			@session_commit();
		$handler=$this->hive['UNLOAD'];
		if ((!$handler || $this->call($handler,$this)===FALSE) &&
			$error && in_array($error['type'],
			array(E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR)))
			// Fatal error detected
			$this->error(sprintf(self::E_Fatal,$error['message']));
	}

	//! Prohibit cloning
	private function __clone() {
	}

	//! Bootstrap
	function __construct() {
		// Managed directives
		ini_set('default_charset',$charset='UTF-8');
		if (extension_loaded('mbstring'))
			mb_internal_encoding($charset);
		ini_set('display_errors',0);
		// Deprecated directives
		@ini_set('magic_quotes_gpc',0);
		@ini_set('register_globals',0);
		// Abort on startup error
		// Intercept errors/exceptions; PHP5.3-compatible
		error_reporting(E_ALL|E_STRICT);
		$fw=$this;
		set_exception_handler(
			function($obj) use($fw) {
				$fw->error(500,$obj->getmessage(),$obj->gettrace());
			}
		);
		set_error_handler(
			function($code,$text) use($fw) {
				if (error_reporting())
					$fw->error(500,$text);
			}
		);
		if (!isset($_SERVER['SERVER_NAME']))
			$_SERVER['SERVER_NAME']=gethostname();
		if (PHP_SAPI=='cli') {
			// Emulate HTTP request
			if (isset($_SERVER['argc']) && $_SERVER['argc']<2) {
				$_SERVER['argc']++;
				$_SERVER['argv'][1]='/';
			}
			$_SERVER['REQUEST_METHOD']='GET';
			$_SERVER['REQUEST_URI']=$_SERVER['argv'][1];
		}
		$headers=array();
		if (PHP_SAPI!='cli')
			foreach (array_keys($_SERVER) as $key)
				if (substr($key,0,5)=='HTTP_')
					$headers[strtr(ucwords(strtolower(strtr(
						substr($key,5),'_',' '))),' ','-')]=&$_SERVER[$key];
		if (isset($headers['X-HTTP-Method-Override']))
			$_SERVER['REQUEST_METHOD']=$headers['X-HTTP-Method-Override'];
		elseif ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['_method']))
			$_SERVER['REQUEST_METHOD']=$_POST['_method'];
		$scheme=isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on' ||
			isset($headers['X-Forwarded-Proto']) &&
			$headers['X-Forwarded-Proto']=='https'?'https':'http';
		if (function_exists('apache_setenv')) {
			// Work around Apache pre-2.4 VirtualDocumentRoot bug
			$_SERVER['DOCUMENT_ROOT']=str_replace($_SERVER['SCRIPT_NAME'],'',
				$_SERVER['SCRIPT_FILENAME']);
			apache_setenv("DOCUMENT_ROOT",$_SERVER['DOCUMENT_ROOT']);
		}
		$_SERVER['DOCUMENT_ROOT']=realpath($_SERVER['DOCUMENT_ROOT']);
		$base='';
		if (PHP_SAPI!='cli')
			$base=rtrim($this->fixslashes(
				dirname($_SERVER['SCRIPT_NAME'])),'/');
		$path=preg_replace('/^'.preg_quote($base,'/').'/','',
			parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
		call_user_func_array('session_set_cookie_params',
			$jar=array(
				'expire'=>0,
				'path'=>$base?:'/',
				'domain'=>is_int(strpos($_SERVER['SERVER_NAME'],'.')) &&
					!filter_var($_SERVER['SERVER_NAME'],FILTER_VALIDATE_IP)?
					$_SERVER['SERVER_NAME']:'',
				'secure'=>($scheme=='https'),
				'httponly'=>TRUE
			)
		);
		// Default configuration
		$this->hive=array(
			'AGENT'=>isset($headers['X-Operamini-Phone-UA'])?
				$headers['X-Operamini-Phone-UA']:
				(isset($headers['X-Skyfire-Phone'])?
					$headers['X-Skyfire-Phone']:
					(isset($headers['User-Agent'])?
						$headers['User-Agent']:'')),
			'AJAX'=>isset($headers['X-Requested-With']) &&
				$headers['X-Requested-With']=='XMLHttpRequest',
			'ALIASES'=>array(),
			'AUTOLOAD'=>'./',
			'BASE'=>$base,
			'BITMASK'=>ENT_COMPAT,
			'BODY'=>NULL,
			'CASELESS'=>TRUE,
			'DEBUG'=>0,
			'DIACRITICS'=>array(),
			'DNSBL'=>'',
			'EMOJI'=>array(),
			'ENCODING'=>$charset,
			'ERROR'=>NULL,
			'ESCAPE'=>TRUE,
			'EXEMPT'=>NULL,
			'FALLBACK'=>$this->fallback,
			'HEADERS'=>$headers,
			'HALT'=>TRUE,
			'HIGHLIGHT'=>TRUE,
			'HOST'=>$_SERVER['SERVER_NAME'],
			'IP'=>isset($headers['Client-IP'])?
				$headers['Client-IP']:
				(isset($headers['X-Forwarded-For'])?
					$headers['X-Forwarded-For']:
					(isset($_SERVER['REMOTE_ADDR'])?
						$_SERVER['REMOTE_ADDR']:'')),
			'JAR'=>$jar,
			'LANGUAGE'=>isset($headers['Accept-Language'])?
				$this->language($headers['Accept-Language']):
				$this->fallback,
			'LOCALES'=>'./',
			'LOGS'=>'./',
			'ONERROR'=>NULL,
			'PACKAGE'=>self::PACKAGE,
			'PARAMS'=>array(),
			'PATH'=>$path,
			'PATTERN'=>NULL,
			'PLUGINS'=>$this->fixslashes(__DIR__).'/',
			'PORT'=>isset($_SERVER['SERVER_PORT'])?
				$_SERVER['SERVER_PORT']:NULL,
			'PREFIX'=>NULL,
			'QUIET'=>FALSE,
			'RAW'=>FALSE,
			'REALM'=>$scheme.'://'.
				$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'],
			'RESPONSE'=>'',
			'ROOT'=>$_SERVER['DOCUMENT_ROOT'],
			'ROUTES'=>array(),
			'SCHEME'=>$scheme,
			'SERIALIZER'=>extension_loaded($ext='igbinary')?$ext:'php',
			'TEMP'=>'tmp/',
			'TIME'=>microtime(TRUE),
			'TZ'=>(@ini_get('date.timezone'))?:'UTC',
			'UI'=>'./',
			'UNLOAD'=>NULL,
			'UPLOADS'=>'./',
			'URI'=>&$_SERVER['REQUEST_URI'],
			'VERB'=>&$_SERVER['REQUEST_METHOD'],
			'VERSION'=>self::VERSION,
			'XFRAME'=>'SAMEORIGIN'
		);
		if (PHP_SAPI=='cli-server' &&
			preg_match('/^'.preg_quote($base,'/').'$/',$this->hive['URI']))
			$this->reroute('/');
		if (ini_get('auto_globals_jit'))
			// Override setting
			$GLOBALS+=array('_ENV'=>$_ENV,'_REQUEST'=>$_REQUEST);
		// Sync PHP globals with corresponding hive keys
		$this->init=$this->hive;
		foreach (explode('|',self::GLOBALS) as $global) {
			$sync=$this->sync($global);
			$this->init+=array(
				$global=>preg_match('/SERVER|ENV/',$global)?$sync:array()
			);
		}
		if ($error=error_get_last())
			// Error detected
			$this->error(500,sprintf(self::E_Fatal,$error['message']),
				array($error));
		date_default_timezone_set($this->hive['TZ']);
		// Register framework autoloader
		spl_autoload_register(array($this,'autoload'));
		// Register shutdown handler
		register_shutdown_function(array($this,'unload'),getcwd());
	}

}

//! Container for singular object instances
final class Registry {

	private static
		//! Object catalog
		$table;

	/**
	*	Return TRUE if object exists in catalog
	*	@return bool
	*	@param $key string
	**/
	static function exists($key) {
		return isset(self::$table[$key]);
	}

	/**
	*	Add object to catalog
	*	@return object
	*	@param $key string
	*	@param $obj object
	**/
	static function set($key,$obj) {
		return self::$table[$key]=$obj;
	}

	/**
	*	Retrieve object from catalog
	*	@return object
	*	@param $key string
	**/
	static function get($key) {
		return self::$table[$key];
	}

	/**
	*	Delete object from catalog
	*	@return NULL
	*	@param $key string
	**/
	static function clear($key) {
		self::$table[$key]=NULL;
		unset(self::$table[$key]);
	}

	//! Prohibit cloning
	private function __clone() {
	}

	//! Prohibit instantiation
	private function __construct() {
	}

}

return Base::instance();