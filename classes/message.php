<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Helper class for working with system-generated messages.
 * Based on BIakaVeron's Message class.
 *
 * @author     Brandon Summers
 * @author     Brotkin Ivan (BIakaVeron) <BIakaVeron@gmail.com>
 * @copyright  Copyright (c) 2009 Brotkin Ivan
 */

class Message {

	/**
	 * @var  array  All messages
	 */
	protected static $_messages = array();
	
	/**
	 * @var  array  Messages that are currently saved in the session
	 */
	protected static $_current_messages = array();
	
	/**
	 * @var  array  Messages that have been added, but not yet saved to session
	 */
	protected static $_new_messages = array();
	
	/**
	 * @var  array  Templates used for rendering
	 */
	protected static $_templates = array();
	
	/**
	 * @var  string  Name of the session variable
	 */
	protected static $_session_var = NULL;
	
	/**
	 * @var  string  Reference to Session::instance()
	 */
	protected static $_session = FALSE;
	
	/**
	 * @var  bool  Determines if the helper has been loaded
	 */
	protected static $_loaded = FALSE;

	const SUCCESS = 'success';
	const ERROR = 'error';
	const NOTICE = 'notice';
	const INFO = 'info';

	/**
	 * Set session instance and load configuration values.
	 * 
	 * @access  public
	 * @return  void
	 */
	public static function init()
	{
		if ( ! self::$_loaded)
		{
			$config = Kohana::config('message');
			
			self::$_templates = $config['templates'];
			self::$_session_var = $config['session_var'];
			
			self::load_messages();
			self::$_loaded = TRUE;
		}
	}

	/**
	 * Saves new messages to the session, overwriting everything else.
	 * 
	 * @access  public
	 * @return  void
	 */
	public static function save()
	{
		if (empty(self::$_new_messages))
			return FALSE;
		
		Session::instance()->set(self::$_session_var, self::$_new_messages);
		
		self::load_messages();
	}

	/**
	 * Saves all messages, current and new, to the session.
	 * Only use if you want to use current messages on the next page.
	 * 
	 * @access  public
	 * @return  void
	 */
	public static function save_all()
	{
		if (empty(self::$_new_messages))
			return FALSE;
		
		self::sync();

		Session::instance()->set(self::$_session_var, self::$_messages);
	}

	/**
	 * Syncronizes all message arrays. Used anytime new messages are added.
	 * 
	 * @access  protected
	 * @param   string     the type of messages to syncronize
	 * @return  void
	 */
	protected static function sync($type = NULL)
	{
		if (is_null($type))
		{
			self::$_messages = self::$_current_messages + self::$_new_messages;
		}
		else
		{
			self::$_messages[$type] = (isset(self::$_current_messages[$type]) ? self::$_current_messages[$type] : array()) + (isset(self::$_new_messages[$type]) ? self::$_new_messages[$type] : array());
		}
	}

	/**
	 * Removes a specific message from the helper and optionally the session.
	 * 
	 * @access  public
	 * @param   string  Type of message to remove
	 * @param   string  The message to remove
	 * @return  void
	 */
	public static function remove($type, $message)
	{
		if (isset(self::$_messages[$type][$message]))
		{
			unset(self::$_messages[$type][$message]);
		}
		
		if (isset(self::$_new_messages[$type][$message]))
		{
			unset(self::$_new_messages[$type][$message]);
		}
		
		if (isset(self::$_current_messages[$type][$message]))
		{
			unset(self::$_current_messages[$type][$message]);
		}
	}

	/**
	 *
	 * Returns all messages of supplied type and removes them from helper
	 * 
	 * @access  public
	 * @param   string  Type of messages to get
	 * @return  array
	 */
	public static function get($type = NULL)
	{
		if (is_null($type))
		{
			$messages = self::$_messages;
			self::clear(NULL, FALSE);
			return $messages;
		}

		if ( ! isset(self::$_messages[$type]))
			return array();

		$messages = self::$_messages[$type];
		self::clear($type, FALSE);

		return $messages;
	}

	/**
	 * Add a message to the session.
	 * 
	 * @param   string|array  message/s to add
	 * @param   string        type of message/s
	 * @param   bool          save the message/s
	 * @return  void
	 */
	public static function add($message, $type = Message::INFO, $save = TRUE)
	{
		if (is_array($message))
		{
			if (isset(self::$_new_messages[$type]))
			{
				self::$_new_messages[$type] += $message;
			}
			else
			{
				self::$_new_messages[$type] = $message;
			}
		}
		else
		{
			if ( ! isset(self::$_new_messages[$type][$message]))
			{
				self::$_new_messages[$type][] = $message;
			}
		}

		self::sync();
		
		if ($save === TRUE)
		{
			self::save();
		}
	}

	/**
	 * Renders the messages into a string.
	 * 
	 * @access  public
	 * @param   string   Message type to render
	 * @return  string
	 */
	public static function render($type = NULL)
	{
		if (count(self::$_messages) == 0)
			return FALSE;

		if (is_null($type))
		{
			$html = '';

			foreach(self::$_messages as $type => $message)
			{
				$html .= self::render($type);
			}
			
			return $html;
		}

		$messages = self::get($type);

		if (count($messages) == 0)
			return FALSE;

		$template = (self::$_templates[$type] != '') ? self::$_templates[$type] : 'default';
		$view = View::factory($template)
			->set('messages', $messages)
			->set('type', $type);

		return $view->render();
	}

	/**
	 * Checks if there are any messages of a certain type.
	 * 
	 * @param   string  Message type to check for
	 * @return  bool
	 */
	public static function has($type = Message::ERROR)
	{
		if (is_array($type))
		{
			foreach($type as $message_type)
			{
				if (self::has($message_type))
					return TRUE;
			}

			return FALSE;
		}

		if ( ! isset(self::$_messages[$type]))
			return FALSE;

		return (bool) count(self::$_messages[$type]);
	}
	
	/**
	 * Gets the current messages from the session.
	 * 
	 * @access  protected
	 * @return  void
	 */
	protected static function load_messages()
	{
		self::$_current_messages = Session::instance()->get(self::$_session_var);
	}
	
	/**
	 * Removes all current messages from the class and optionally the session.
	 * 
	 * @access  protected
	 * @param   string     Message type to delete
	 * @param   bool       Clear the session as well
	 * @return  void
	 */
	protected static function clear($type = NULL, $session = TRUE)
	{
		if (is_null($type))
		{
			self::$_messages = self::$_new_messages = self::$_current_messages = array();
		}
		else
		{
			if (isset(self::$_messages[$type]))
			{
				unset(self::$_messages[$type]);
			}
			
			if (isset(self::$_new_messages[$type]))
			{
				unset(self::$_new_messages[$type]);
			}
			
			if (isset(self::$_current_messages[$type]))
			{
				unset(self::$_current_messages[$type]);
			}
		}
		
		if ($session)
		{
			self::save();
		}
	}

}