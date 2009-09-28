<?php

/**
 * Twitter for PHP - library for sending messages to Twitter and receiving status updates.
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2008 David Grudl
 * @license    New BSD License
 * @link       http://phpfashion.com/
 * @version    1.1
 */
class Twitter
{
	/** @var int */
	public static $cacheExpire = 1800; // 30 min

	/** @var string */
	public static $cacheDir;

	/** @var  user name */
	private $user;

	/** @var  password */
	private $pass;



	/**
	 * Creates object using your credentials.
	 * @param  string  user name
	 * @param  string  password
	 * @throws Exception
	 */
	public function __construct($user, $pass)
	{
		if (!extension_loaded('curl')) {
			throw new TwitterException('PHP extension CURL is not loaded.');
		}

		$this->user = $user;
		$this->pass = $pass;
	}



	/**
	 * Tests if user credentials are valid.
	 * @return boolean
	 * @throws Exception
	 */
	public function authenticate()
	{
		$xml = $this->httpRequest('http://twitter.com/account/verify_credentials.xml');
		return empty($xml->error) && !empty($xml->id);
	}



	/**
	 * Sends message to the Twitter.
	 * @param string   message encoded in UTF-8
	 * @return mixed   ID on success or FALSE on failure
	 */
	public function send($message)
	{
		$xml = $this->httpRequest(
			'https://twitter.com/statuses/update.xml',
			array('status' => $message)
		);
		return $xml->id ? (string) $xml->id : FALSE;
	}



	/**
	 * Returns the most recent statuses posted from you and your friends (optionally).
	 * @param  bool  with friends?
	 * @param  int   number of statuses to retrieve
	 * @param  int   page of results to retrieve
	 * @return SimpleXMLElement
	 * @throws TwitterException
	 */
	public function load($withFriends, $count = 20, $page = 1)
	{
		$line = $withFriends ? 'friends_timeline' : 'user_timeline';
		$xml = $this->cachedHttpRequest("http://twitter.com/statuses/$line/$this->user.xml?count=$count&page=$page");
		if (isset($xml->error)) {
			throw new TwitterException($xml->error);
		}
		return $xml;
	}



	/**
	 * Process HTTP request.
	 * @param string  URL
	 * @param array   of post data
	 * @return SimpleXMLElement
	 */
	private function httpRequest($url, $post = NULL)
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_USERPWD, "$this->user:$this->pass");
		curl_setopt($curl, CURLOPT_HEADER, FALSE);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Expect:'));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE); // no echo, just return result
		if ($post) {
			curl_setopt($curl, CURLOPT_POST, TRUE);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		}

		$result = curl_exec($curl);
		if (curl_errno($curl) === 0) {
			@$xml = simplexml_load_string($result); // intentionally @
			if ($xml) {
				return $xml;
			}
		}

		throw new TwitterException('Invalid server response');
	}



	/**
	 * Cached HTTP request.
	 * @param string  URL
	 * @return SimpleXMLElement
	 */
	private function cachedHttpRequest($url)
	{
		if (!self::$cacheDir) {
			return $this->httpRequest($url);
		}

		$cacheFile = self::$cacheDir . '/twitter.' . md5($url) . '.xml';
		$cache = @simplexml_load_string(file_get_contents($cacheFile)); // intentionally @
		if ($cache && @filemtime($cacheFile) + self::$cacheExpire > time()) { // intentionally @
			return $cache;
		}

		try {
			$xml = $this->httpRequest($url);
			file_put_contents($cacheFile, $xml->asXml());
			return $xml;

		} catch (TwitterException $e) {
			if ($cache) {
				return $cache;
			}
			throw $e;
		}
	}

}



/**
 * An exception generated by Twitter.
 */
class TwitterException extends Exception
{
}