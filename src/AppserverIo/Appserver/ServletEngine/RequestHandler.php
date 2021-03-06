<?php

/**
 * \AppserverIo\Appserver\ServletEngine\RequestHandler
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
use AppserverIo\Appserver\ServletEngine\Http\Response;
use AppserverIo\Psr\HttpMessage\ResponseInterface;
use AppserverIo\Psr\Application\ApplicationInterface;
use AppserverIo\Psr\Servlet\ServletException;
use AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface;
use AppserverIo\Psr\Servlet\Http\HttpServletResponseInterface;
use AppserverIo\Server\Exceptions\ModuleException;

/**
 * This is a request handler that is necessary to process each request of an
 * application in a separate context.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 *
 * @property \AppserverIo\Psr\Application\ApplicationInterface          $application     The application instance
 * @property \AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface  $servletRequest  The actual request instance
 * @property \AppserverIo\Psr\Servlet\Http\HttpServletResponseInterface $servletResponse The actual response instance
 * @property \AppserverIo\Storage\GenericStackable                      $valves          The valves to process
 */
class RequestHandler extends \Thread
{

    /**
     * Injects the valves to be processed.
     *
     * @param array $valves The valves to process
     *
     * @return void
     */
    public function injectValves(array $valves)
    {
        $this->valves = $valves;
    }

    /**
     * Injects the application of the request to be handled
     *
     * @param \AppserverIo\Psr\Application\ApplicationInterface $application The application instance
     *
     * @return void
     */
    public function injectApplication(ApplicationInterface $application)
    {
        $this->application = $application;
    }

    /**
     * Inject the actual servlet request.
     *
     * @param \AppserverIo\Psr\Servlet\Http\HttpServletRequestInterface $servletRequest The actual request instance
     *
     * @return void
     */
    public function injectRequest(HttpServletRequestInterface $servletRequest)
    {
        $this->servletRequest = $servletRequest;
    }

    /**
     * The main method that handles the thread in a separate context.
     *
     * @return void
     */
    public function run()
    {

        try {
            // register the default autoloader
            require SERVER_AUTOLOADER;

            // register shutdown handler
            register_shutdown_function(array(&$this, "shutdown"));

            // synchronize the application instance and register the class loaders
            $application = $this->application;
            $application->registerClassLoaders();

            // synchronize the valves, servlet request/response
            $valves = $this->valves;
            $servletRequest = $this->servletRequest;

            // initialize servlet session, request + response
            $servletResponse = new Response();
            $servletResponse->init();

            // we initialize this with a 500 to handle 'Fatal Error' case
            $this->statusCode = 500;

            // initialize arrays for header and cookies
            $this->state = $servletResponse->getState();
            $this->version = $servletResponse->getVersion();
            $this->headers = $servletResponse->getHeaders();
            $this->cookies = $servletResponse->getCookies();

            // inject the sapplication and servlet response
            $servletRequest->injectResponse($servletResponse);
            $servletRequest->injectContext($application);

            // prepare the request instance
            $servletRequest->prepare();

            // process the valves
            foreach ($valves as $valve) {
                $valve->invoke($servletRequest, $servletResponse);
                if ($servletRequest->isDispatched() === true) {
                    break;
                }
            }

            // profile the request if the profile logger is available
            if ($profileLogger = $application->getInitialContext()->getLogger(LoggerUtils::PROFILE)) {
                $profileLogger->appendThreadContext('request-handler');
                $profileLogger->debug($servletRequest->getUri());
            }

        } catch (\Exception $e) {
            // log the exception
            $application->getInitialContext()->getSystemLogger()->error($e->__toString());

            // ATTENTION: We MUST wrap the exception, because it's possible that
            //            the exception contains not serializable data that will
            //            lead to a white page!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            $this->exception = new ServletException($e, $e->getCode());
        }

        // copy the the response values
        $this->statusCode = $servletResponse->getStatusCode();
        $this->statusReasonPhrase = $servletResponse->getStatusReasonPhrase();
        $this->version = $servletResponse->getVersion();
        $this->state = $servletResponse->getState();

        // copy the content of the body stream
        $this->bodyStream = $servletResponse->getBodyStream();

        // copy headers and cookies
        $this->headers = $servletResponse->getHeaders();
        $this->cookies = $servletResponse->getCookies();
    }

    /**
     * Copies the values from the request handler back to the passed HTTP response instance.
     *
     * @param \AppserverIo\Psr\HttpMessage\ResponseInterface $httpResponse A HTTP response object
     *
     * @return void
     */
    public function copyToHttpResponse(ResponseInterface $httpResponse)
    {

        // copy response values to the HTTP response
        $httpResponse->setStatusCode($this->statusCode);
        $httpResponse->setStatusReasonPhrase($this->statusReasonPhrase);
        $httpResponse->setVersion($this->version);
        $httpResponse->setState($this->state);

        // copy the body content to the HTTP response
        $httpResponse->appendBodyStream($this->bodyStream);

        // copy headers to the HTTP response
        foreach ($this->headers as $headerName => $headerValue) {
            $httpResponse->addHeader($headerName, $headerValue);
        }

        // copy cookies to the HTTP response
        $httpResponse->setCookies($this->cookies);

        // query whether an exception has been thrown, if yes, re-throw it
        if ($this->exception instanceof \Exception) {
            throw $this->exception;
        }
    }

    /**
     * Does shutdown logic for request handler if something went wrong and
     * produces a fatal error for example.
     *
     * @return void
     */
    public function shutdown()
    {

        // check if there was a fatal error caused shutdown
        if ($lastError = error_get_last()) {
            // initialize type + message
            $type = 0;
            $message = '';
            // extract the last error values
            extract($lastError);
            // query whether we've a fatal/user error
            if ($type === E_ERROR || $type === E_USER_ERROR) {
                $this->exception = new ServletException($message, 500);
            }
        }
    }
}
