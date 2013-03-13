<?php
namespace Rocker;

use Rocker\Utils\ErrorHandler;


/**
 * Rocker server application
 *
 * @package Rocker
 * @author Victor Jonsson (http://victorjonsson.se)
 * @license MIT license (http://opensource.org/licenses/MIT)
 */
class Server extends \Slim\Slim  {

    /**
     * @const Current version of Rocker
     */
    const VERSION = '0.9.14';

    /**
     * @param array $config
     * @param bool $initErrorHandler
     */
    function __construct(array $config, $initErrorHandler=true)
    {
        if( $initErrorHandler ) {
            ErrorHandler::init($config);
        }

        parent::__construct($config);

        $basePath = trim($this->settings['application.path']);
        if( $basePath != '/' ){
            $basePath = '/'.trim($basePath, '/').'/';
        }

        $this->map($basePath.':args+', array($this, 'handleAPIRequest'))->via('GET', 'POST', 'HEAD', 'PUT', 'DELETE');
    }

    /**
     * Handles request and echos response to client
     * @param array $path
     */
    public function handleAPIRequest($path)
    {
        try {
            $db = \Rocker\Object\DB::instance($this->config('application.db'));
            $cache = \Rocker\Cache\CacheLoader::instance($this->config('application.cache'));
            $controller = new \Rocker\REST\RequestController($this, $db, $cache);

            $controller->handle($path);

        } catch(\InvalidArgumentException $e) {

            $response = new REST\OperationResponse(400, array('error'=>$e->getMessage()));
            $controller = new REST\RequestController($this, null, null);
            $controller->handleResponse( $response );

        } catch(\Exception $e) {

            ErrorHandler::log($e);

            $mess = array('message'=>$e->getMessage());
            if( $this->config('mode') == 'development' ) {
                $mess['trace'] = $e->getTrace();
            }
            $response = new REST\OperationResponse(500, $mess);
            $controller = new REST\RequestController($this, null, null);
            $controller->handleResponse( $response );
        }
    }

    /**
     * This function is overridden to prevent slim from adding
     * its own exception handler (PrettyExceptions, rockers exception handler
     * is pretty enough). If updating to a newer version of slim make sure you
     * take a look in case this function have changed
     */
    public function run()
    {
        //Invoke middleware and application stack
        $this->middleware[0]->call();

        //Fetch status, header, and body
        list($status, $header, $body) = $this->response->finalize();

        //Send headers
        if (headers_sent() === false) {
            //Send status
            if (strpos(PHP_SAPI, 'cgi') === 0) {
                header(sprintf('Status: %s', \Slim\Http\Response::getMessageForCode($status)));
            } else {
                header(sprintf('HTTP/%s %s', $this->config('http.version'), \Slim\Http\Response::getMessageForCode($status)));
            }

            //Send headers
            foreach ($header as $name => $value) {
                $hValues = explode("\n", $value);
                foreach ($hValues as $hVal) {
                    header("$name: $hVal", false);
                }
            }
        }

        // Send body
        echo $body;
    }

    /**
     * Redirects client to current page
     */
    public function reload()
    {
        $this->redirect($this->request()->getPath());
        die;
    }
}