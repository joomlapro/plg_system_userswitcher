<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.Userswitcher
 *
 * @copyright   Copyright (C) 2013 AtomTech, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access.
defined('_JEXEC') or die;

/**
 * Joomla Userswitcher plugin.
 *
 * @package     Joomla.Plugin
 * @subpackage  System.Userswitcher
 * @since       3.1
 */
class PlgSystemUserswitcher extends JPlugin
{
	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe.
	 * @param   array   $config    An array that holds the plugin configuration.
	 *
	 * @access  protected
	 * @since   3.1
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);

		$this->loadLanguage();
	}

	/**
	 * Before the framework renders the application.
	 *
	 * @return  void
	 *
	 * @since   3.1
	 */
	public function onBeforeRender()
	{
		// Get the application.
		$app = JFactory::getApplication();

		// Detecting Active Variables.
		$option = $app->input->getCmd('option', '');
		$view   = $app->input->getCmd('view', '');
		$layout = $app->input->getCmd('layout', '');
		$id     = $app->input->getInt('id', '');

		// Only in Admin.
		if ($app->isSite())
		{
			return;
		}

		if ($option == 'com_users' && $view == 'user' && $layout == 'edit' && $id != 0)
		{
			// Get the toolbar object instance.
			$toolbar = JToolBar::getInstance('toolbar');
			$toolbar->appendButton('Link', 'user', 'JLOGIN', JUri::root() . 'index.php?su=1&uid=' . $id);
		}
	}

	/**
	 * After framework load and application initialise.
	 *
	 * @return  mixed
	 *
	 * @since   3.1
	 */
	public function onAfterInitialise()
	{
		// Initialiase variables.
		$app     = JFactory::getApplication();
		$user    = JFactory::getUser();
		$session = JFactory::getSession();
		$db      = JFactory::getDbo();
		$su      = $app->input->getInt('su', 0);
		$userId  = $app->input->getInt('uid', 0);

		// Only in Site and only if the variables $su and $userId exists.
		if ($app->isAdmin() || $su != 1 || $userId == 0)
		{
			return;
		}

		// Check the current session.
		if (!$this->checkSession())
		{
			return $app->redirect(JRoute::_('index.php'), JText::_('JLIB_ENVIRONMENT_SESSION_EXPIRED'));
		}

		// Check if user already logged.
		if ($user->id == $userId)
		{
			return $app->redirect(JRoute::_('index.php'), JText::sprintf('PLG_SYSTEM_MESSAGE_ALREADY_LOGGED', $user->name), 'warning');
		}

		// Check to see if we're deleting the current session.
		if ($user->id)
		{
			// Hit the user last visit field.
			$user->setLastVisit();

			// Force logout all users with that userid.
			$query = $db->getQuery(true)
				->delete($db->quoteName('#__session'))
				->where($db->quoteName('client_id') . ' = ' . $db->quote('0'))
				->where($db->quoteName('userid') . ' = ' . $db->quote((int) $user->id));
			$db->setQuery($query);
			$db->execute();
		}

		$instance = JFactory::getUser($userId);

		// If getUser returned an error, then pass it back.
		if ($instance instanceof Exception)
		{
			return $app->redirect(JRoute::_('index.php'), JText::_('JERROR_LOGIN_DENIED'), 'error');
		}

		// If the user is blocked, redirect with an error.
		if ($instance->get('block') == 1)
		{
			return $app->redirect(JRoute::_('index.php'), JText::_('JERROR_NOLOGIN_BLOCKED'), 'error');
		}

		// Mark the user as logged in.
		$instance->set('guest', 0);

		// Register the needed session variables.
		$session->set('user', $instance);

		// Check to see the the session already exists.
		$app->checkSession();

		// Update the user related fields for the Joomla sessions table.
		$query = $db->getQuery(true)
			->update($db->quoteName('#__session'))
			->set($db->quoteName('guest') . ' = ' . $db->quote($instance->get('guest')))
			->set($db->quoteName('userid') . ' = ' . (int) $instance->get('id'))
			->set($db->quoteName('username') . ' = ' . $db->quote($instance->get('username')))
			->where($db->quoteName('session_id') . ' = ' . $db->quote($session->getId()));
		$db->setQuery($query);
		$db->execute();

		// Hit the user last visit field.
		$instance->setLastVisit();

		return $app->redirect(JRoute::_('index.php'), JText::sprintf('PLG_SYSTEM_MESSAGE_SUCCESSFULLY_LOGGED', $instance->name));
	}

	/**
	 * Method to check the session
	 *
	 * @return  boolean
	 *
	 * @since   3.1
	 */
	public function checkSession()
	{
		// Get the input.
		$input = JFactory::getApplication()->input;

		// Initialiase variables.
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		// Create the base select statement.
		$query = $db->getQuery(true)
			->select('userid')
			->from($db->quoteName('#__session'))
			->where($db->quoteName('session_id') . ' = ' . $db->quote($input->cookie->get(md5(JApplication::getHash('administrator')))))
			->where($db->quoteName('client_id') . ' = ' . $db->quote('1'))
			->where($db->quoteName('guest') . ' = ' . $db->quote('0'));

		// Set the query and load the result.
		$db->setQuery($query);

		try
		{
			$db->loadResult();
		}
		catch (RuntimeException $e)
		{
			throw new RuntimeException($e->getMessage(), $e->getCode());
		}

		return true;
	}
}
