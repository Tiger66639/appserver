<?php

/**
 * \AppserverIo\Appserver\ServletEngine\FilesystemPersistenceManager
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */

namespace AppserverIo\Appserver\ServletEngine;

use AppserverIo\Logger\LoggerUtils;
use AppserverIo\Appserver\Core\AbstractDaemonThread;
use AppserverIo\Psr\Servlet\ServletSessionInterface;

/**
 * A thread which pre-initializes session instances and adds them to the
 * the session pool.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 *
 * @property array                                                           $loggers           The logger instances
 * @property \AppserverIo\Storage\StorageInterface                           $sessions          The sessions
 * @property \AppserverIo\Storage\StorageInterface                           $checksums         The checksums
 * @property \AppserverIo\Appserver\ServletEngine\SessionSettingsInterface   $sessionSettings   Settings for the session handling
 * @property \AppserverIo\Appserver\ServletEngine\SessionMarshallerInterface $sessionMarshaller The session marshaller instance
 * @property \AppserverIo\Appserver\ServletEngine\SessionFactory             $sessionFactory    The session factory instance
 * @property string                                                          $user              The user used for file system interactions
 * @property string                                                          $group             The user group used for file system interactions
 * @property string                                                          $umask             The umask used for file system interactions
 */
class FilesystemPersistenceManager extends AbstractDaemonThread implements PersistenceManagerInterface
{

    /**
     * Initializes the session persistence manager.
     */
    public function __construct()
    {

        // initialize the class members
        $this->sessions = null;
        $this->checksums = null;
        $this->sessionMarshaller = null;
        $this->sessionFactory = null;
        $this->sessionSettings = null;

        // initialize the class members with default values
        $this->user = 'nobody';
        $this->group = 'nobody';
        $this->umask = 0002;
    }

    /**
     * Injects the available logger instances.
     *
     * @param array $loggers The logger instances
     *
     * @return void
     */
    public function injectLoggers(array $loggers)
    {
        $this->loggers = $loggers;
    }

    /**
     * Injects the sessions.
     *
     * @param \AppserverIo\Storage\StorageInterface $sessions The sessions
     *
     * @return void
     */
    public function injectSessions($sessions)
    {
        $this->sessions = $sessions;
    }

    /**
     * Injects the checksums.
     *
     * @param \AppserverIo\Storage\StorageInterface $checksums The checksums
     *
     * @return void
     */
    public function injectChecksums($checksums)
    {
        $this->checksums = $checksums;
    }

    /**
     * Injects the session settings.
     *
     * @param \AppserverIo\Appserver\ServletEngine\SessionSettingsInterface $sessionSettings Settings for the session handling
     *
     * @return void
     */
    public function injectSessionSettings($sessionSettings)
    {
        $this->sessionSettings = $sessionSettings;
    }

    /**
     * Injects the session marshaller.
     *
     * @param \AppserverIo\Appserver\ServletEngine\SessionMarshallerInterface $sessionMarshaller The session marshaller instance
     *
     * @return void
     */
    public function injectSessionMarshaller($sessionMarshaller)
    {
        $this->sessionMarshaller = $sessionMarshaller;
    }

    /**
     * Injects the session factory.
     *
     * @param \AppserverIo\Appserver\ServletEngine\SessionFactory $sessionFactory The session factory instance
     *
     * @return void
     */
    public function injectSessionFactory($sessionFactory)
    {
        $this->sessionFactory = $sessionFactory;
    }

    /**
     * Injects the user.
     *
     * @param string $user The user
     *
     * @return void
     */
    public function injectUser($user)
    {
        $this->user = $user;
    }

    /**
     * Injects the group.
     *
     * @param string $group The group
     *
     * @return void
     */
    public function injectGroup($group)
    {
        $this->group = $group;
    }

    /**
     * Injects the umask.
     *
     * @param integer $umask The umask
     *
     * @return void
     */
    public function injectUmask($umask)
    {
        $this->umask = $umask;
    }

    /**
     * Returns the session checksum storage to watch changed sessions.
     *
     * @return \AppserverIo\Storage\StorageInterface The session checksum storage
     */
    public function getChecksums()
    {
        return $this->checksums;
    }

    /**
     * Returns all sessions actually attached to the session manager.
     *
     * @return \AppserverIo\Storage\StorageInterface The container with sessions
     */
    public function getSessions()
    {
        return $this->sessions;
    }

    /**
     * Returns the session settings.
     *
     * @return \AppserverIo\Appserver\ServletEngine\SessionSettingsInterface The session settings
     */
    public function getSessionSettings()
    {
        return $this->sessionSettings;
    }

    /**
     * Returns the session marshaller.
     *
     * @return \AppserverIo\Appserver\ServletEngine\SessionMarshallerInterface The session marshaller
     */
    public function getSessionMarshaller()
    {
        return $this->sessionMarshaller;
    }

    /**
     * Returns the session factory.
     *
     * @return \AppserverIo\Appserver\ServletEngine\SessionFactory The session factory
     */
    public function getSessionFactory()
    {
        return $this->sessionFactory;
    }

    /**
     * Returns the system user.
     *
     * @return string The system user
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Returns the system group.
     *
     * @return string The system user
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * Returns the preferred umask.
     *
     * @return integer The preferred umask
     */
    public function getUmask()
    {
        return $this->umask;
    }

    /**
     * This method will be invoked before the while() loop starts and can be used
     * to implement some bootstrap functionality.
     *
     * @return void
     */
    public function bootstrap()
    {

        // setup autoloader
        require SERVER_AUTOLOADER;

        // try to load the profile logger
        if (isset($this->loggers[LoggerUtils::PROFILE])) {
            $this->profileLogger = $this->loggers[LoggerUtils::PROFILE];
            $this->profileLogger->appendThreadContext('filesystem-persistence-manager');
        }
    }

    /**
     * Returns the default timeout.
     *
     * @return integer The default timeout in microseconds
     */
    public function getDefaultTimeout()
    {
        return parent::getDefaultTimeout() * 5;
    }

    /**
     * This is invoked on every iteration of the daemons while() loop.
     *
     * @param integer $timeout The timeout before the daemon wakes up
     *
     * @return void
     */
    public function iterate($timeout)
    {

        // call parent method and sleep for the default timeout
        parent::iterate($timeout);

        // now persist inactive sessions
        $this->persist();

        // profile the size of the sessions
        if ($this->profileLogger) {
            $this->profileLogger->debug(
                sprintf('Persisted sessions to filesystem for sessions size: %d', sizeof($this->getSessions()))
            );
        }
    }

    /**
     * This method will be invoked by the engine after the
     * servlet has been serviced.
     *
     * @return void
     */
    public function persist()
    {

        // we want to know what inactivity timeout we've to check the sessions for
        $inactivityTimeout = $this->getSessionSettings()->getInactivityTimeout();

        // iterate over all the checksums (session that are active and loaded)
        foreach ($this->getSessions() as $id => $session) {
            // if we found a session
            if ($session instanceof ServletSessionInterface) {
                // if we don't have a checksum, this is a new session
                $checksum = null;
                if ($this->getChecksums()->has($id)) {
                    $checksum = $this->getChecksums()->get($id);
                }

                // load the sessions last activity timestamp
                $lastActivitySecondsAgo = time() - $session->getLastActivityTimestamp();

                // if the session doesn't change, and the last activity is < the inactivity timeout (1440 by default)
                if ($session->getId() != null && $checksum === $session->checksum() && $lastActivitySecondsAgo < $inactivityTimeout) {
                    continue;
                }

                // we want to detach the session (to free memory), when the last activity is > the inactivity timeout (1440 by default)
                if ($session->getId() != null && $checksum === $session->checksum() && $lastActivitySecondsAgo > $inactivityTimeout) {
                    // prepare the session filename
                    $sessionFilename = $this->getSessionSavePath($this->getSessionSettings()->getSessionFilePrefix() . $id);

                    // update the checksum and the file that stores the session data
                    file_put_contents($sessionFilename, $this->marshall($session));

                    // remove the session instance from the session factory
                    $this->getSessionFactory()->removeBySessionId($id);

                    // remove the session instance from the session manager
                    $this->getChecksums()->remove($id);
                    $this->getSessions()->remove($id);
                    continue;
                }

                // we want to persist the session because its data has been changed
                if ($session->getId() != null && $checksum !== $session->checksum()) {
                    // prepare the session filename
                    $sessionFilename = $this->getSessionSavePath($this->getSessionSettings()->getSessionFilePrefix() . $id);

                    // update the checksum and the file that stores the session data
                    file_put_contents($sessionFilename, $this->marshall($session));
                    $this->getChecksums()->set($id, $session->checksum());
                    continue;
                }

                // we want to remove the session file, because the session has been destroyed
                if ($session->getId() == null && $checksum !== $session->checksum()) {
                    // prepare the session filename
                    $sessionFilename = $this->getSessionSavePath($this->getSessionSettings()->getSessionFilePrefix() . $id);
                    // delete the file containing the session data if available
                    $this->removeSessionFile($sessionFilename);

                    // remove the session instance from the session factory
                    $this->getSessionFactory()->removeBySessionId($id);

                    // remove the session instance from the session manager
                    $this->getChecksums()->remove($id);
                    $this->getSessions()->remove($id);
                }
            }
        }
    }

    /**
     * Returns the default path to persist sessions.
     *
     * @param string $toAppend A relative path to append to the session save path
     *
     * @return string The default path to persist session
     */
    public function getSessionSavePath($toAppend = null)
    {
        // load the default path
        $sessionSavePath = $this->getSessionSettings()->getSessionSavePath();

        // check if we've something to append
        if ($toAppend != null) {
            $sessionSavePath = $sessionSavePath . DIRECTORY_SEPARATOR . $toAppend;
        }

        // return the session save path
        return $sessionSavePath;
    }

    /**
     * Initializes the session manager instance and unpersists the all sessions that has
     * been used during the time defined with the last inactivity timeout defined in the
     * session configuration.
     *
     * If the session data could not be loaded, because the files data is corrupt, the
     * file with the session data will be deleted.
     *
     * @return void
     */
    public function initialize()
    {

        // prepare the glob to load the session
        $glob = $this->getSessionSavePath($this->getSessionSettings()->getSessionFilePrefix() . '*');

        // we want to filter the session we initialize on server start
        $sessionFilter = new SessionFilter(new \GlobIterator($glob), $this->getSessionSettings()->getInactivityTimeout());

        // iterate through all session files and initialize them
        foreach ($sessionFilter as $sessionFile) {
            try {
                // unpersist the session data itself
                $this->loadSessionFromFile($sessionFile->getPathname());

            } catch (SessionDataNotReadableException $sdnre) {
                // this maybe happens when the session file is corrupt
                $this->removeSessionFile($sessionFile->getPathname());
            }
        }
    }

    /**
     * Unpersists the session with the passed ID from the persistence layer and
     * reattaches it to the internal session storage.
     *
     * @param string $id The ID of the session we want to unpersist
     *
     * @return void
     */
    protected function unpersist($id)
    {

        try {
            // try to load the session with the passed ID
            if ($this->getSessions()->has($id) === false) {
                // prepare the pathname to the file containing the session data
                $filename = $this->getSessionSettings()->getSessionFilePrefix() . $id;
                $pathname = $this->getSessionSavePath($filename);

                // unpersist the session data itself
                $this->loadSessionFromFile($pathname);
            }

        } catch (SessionDataNotReadableException $sdnre) {
            // this maybe happens when the session file is corrupt
            $this->removeSessionFile($pathname);
        }
    }

    /**
     * Checks if a file with the passed name containing session data exists.
     *
     * @param string $pathname The path of the file to check
     *
     * @return boolean TRUE if the file exists, else FALSE
     */
    public function sessionFileExists($pathname)
    {
        return file_exists($pathname);
    }

    /**
     * Removes the session file with the passed name containing session data.
     *
     * @param string $pathname The path of the file to remove
     *
     * @return boolean TRUE if the file has successfully been removed, else FALSE
     */
    public function removeSessionFile($pathname)
    {
        if (file_exists($pathname)) {
            return unlink($pathname);
        }
        return false;
    }

    /**
     * Tries to load the session data from the passed filename.
     *
     * @param string $pathname The path of the file to load the session data from
     *
     * @return void
     * @throws \AppserverIo\Appserver\ServletEngine\SessionDataNotReadableException Is thrown if the file containing the session data is not readable
     */
    public function loadSessionFromFile($pathname)
    {

        // the requested session file is not a valid file
        if ($this->sessionFileExists($pathname) === false) {
            return;
        }

        // decode the session from the filesystem
        if (($marshalled = file_get_contents($pathname)) === false) {
            throw new SessionDataNotReadableException(sprintf('Can\'t load session data from file %s', $pathname));
        }

        // create a new session instance from the marshaled object representation
        $session = $this->unmarshall($marshalled);

        // load session ID and checksum
        $id = $session->getId();
        $checksum = $session->checksum();

        // add the sessions checksum
        $this->getChecksums()->set($id, $checksum);

        // add the session to the sessions
        $this->getSessions()->set($id, $session);
    }

    /**
     * Initializes the session instance from the passed JSON string. If the encoded
     * data contains objects, they will be unserialized before reattached to the
     * session instance.
     *
     * @param string $marshalled The marshaled session representation
     *
     * @return \AppserverIo\Psr\Servlet\ServletSessionInterface The un-marshaled servlet session instance
     */
    public function unmarshall($marshalled)
    {

        // create a new and empty servlet session instance
        $servletSession = $this->getSessionFactory()->nextFromPool();

        // unmarshall the session data
        $this->getSessionMarshaller()->unmarshall($servletSession, $marshalled);

        // returns the initialized servlet session instance
        return $servletSession;
    }

    /**
     * Transforms the passed session instance into a JSON encoded string. If the data contains
     * objects, each of them will be serialized before store them to the persistence layer.
     *
     * @param \AppserverIo\Psr\Servlet\ServletSessionInterface $servletSession The servlet session to be transformed
     *
     * @return string The marshalled servlet session representation
     */
    public function marshall(ServletSessionInterface $servletSession)
    {
        return $this->getSessionMarshaller()->marshall($servletSession);
    }
}
