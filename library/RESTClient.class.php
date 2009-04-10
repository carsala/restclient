<?
/* vim: set expandtab sw=4 ts=4 sts=4: */
/*
 * Copyright (c) 2009, Carsala Inc 
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 
 *     * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above
 *     copyright notice, this list of conditions and the following
 *     disclaimer in the documentation and/or other materials provided
 *     with the distribution.  
 *     * Neither the name of the Carsala Inc nor the names of its 
 *     contributors may be used to endorse or promote products derived 
 *     from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */


/*
 * RESTClient
 * @package REST
 */


/**
 * A simple REST Client for working with 'high rest' services that support
 * GET, PUT, POST, and DELETE (other verbs can be added as needed). The
 * interface has been created to support the following:
 *
 * $rc = new RESTClient('http://example.com/rest/resource/');
 * $rc->get($data, [$headers, $auth]);
 * $rc->post($data, [$headers, $auth]);
 * $rc->put($data, [$headers, $auth]);
 * $rc->delete();
 *
 * @package REST
 */
class RESTClient 
{

    private $url = null;        
    private $curl = null;

    const USERAGENT = "YourAgent v1.0";

    const METHOD_GET     = 1;
    const METHOD_POST    = 2;
    const METHOD_PUT     = 3;
    const METHOD_DELETE  = 4;
    const METHOD_HEAD    = 5; // not implemented

    function __construct()
    {
        // Allow array('url' => $value)
        if (func_num_args() == 1 and is_array(func_get_arg(0))) {
            $params = func_get_args(0); 
            if (isset($params[0]) and is_array($params[0])) {
                $vars = get_class_vars(__CLASS__);
                foreach ($vars as $name => $value) {
                    if (isset($params[0][$name])) {
                        $this->$name = $params[0][$name];
                    }
                }
            }
        }
    }



    /**
     * POST
     *
     * @return RESTResponse
     * @param mixed the data to POST
     * @param array any additional header values to include in the request
     * @param array containing username, password, and optionally authtype
     *              the default is DIGEST
     */
    function post($data=null, $header=array(), $auth=array())
    {
        $this->_setupCurl($this->url, RESTClient::METHOD_POST, $header, $auth);

        if (isset($data)) {
            $postdata = http_build_query($data);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postdata);
        } else {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, null);
        }

        $result = curl_exec($this->curl);

        $ret = new RESTResponse(
            array(
                'body' => $result, 
                'code' => curl_getinfo($this->curl, CURLINFO_HTTP_CODE)
            )
        );

        curl_close($this->curl);

        return $ret;
    }


    /**
     * GET
     *
     * @return RESTResponse
     * @param mixed the data sent to GET
     * @param array any additional header values to include in the request
     * @param array containing username, password, and optionally authtype
     *              the default is DIGEST
     */
    function get($data=null, $header=array(), $auth=array())
    {
        if (isset($data)) {
            $getdata = http_build_query($data);
            $url = sprintf("%s?%s", $this->url, $getdata);
        } else {
            $url = $this->url;
        }

        $this->_setupCurl($url, RESTClient::METHOD_GET, $header, $auth);
        $result = curl_exec($this->curl);

        $ret = new RESTResponse(
            array(
                'body' => $result, 
                'code' => curl_getinfo($this->curl, CURLINFO_HTTP_CODE)
            )
        );

        curl_close($this->curl);

        return $ret;
    }

    

    /**
     * PUT
     *
     * @return RESTResponse
     * @param mixed the data to PUT
     * @param array any additional header values to include in the request
     * @param array containing username, password, and optionally authtype
     *              the default is DIGEST
     */
    function put($data=null, $header=array(), $auth=array())
    {
        $this->_setupCurl($this->url, RESTClient::METHOD_PUT, $header, $auth);

        if (isset($data)) {
            $putdata = http_build_query($data);
            $fh = fopen('php://memory', 'rw');
            fwrite($fh, $putdata);
            rewind($fh);
            curl_setopt($this->curl, CURLOPT_INFILE, $fh);
            curl_setopt($this->curl, CURLOPT_INFILESIZE, strlen($putdata));
            $result = curl_exec($this->curl);
            fclose($fh);
        } else {
            curl_setopt($this->curl, CURLOPT_INFILESIZE, 0);
            $result = curl_exec($this->curl);
        }


        $ret = new RESTResponse(
            array(
                'body' => $result, 
                'code' => curl_getinfo($this->curl, CURLINFO_HTTP_CODE)
            )
        );

        curl_close($this->curl);

        return $ret;

    }



    /**
     * DELETE
     *
     * @return RESTResponse
     * @param array any additional header values to include in the request
     * @param array containing username, password, and optionally authtype
     *              the default is DIGEST
     */
    function delete($header=array(), $auth=array())
    {
        $this->_setupCurl($this->url, RESTClient::METHOD_DELETE, $header, $auth);
        $result = curl_exec($this->curl);

        $ret = new RESTResponse(
            array(
                'body' => $result, 
                'code' => curl_getinfo($this->curl, CURLINFO_HTTP_CODE)
            )
        );

        curl_close($this->curl);

        return $ret;
    }




    /**
     * Internal setup function. This method does most of the grunt work for
     * setting up the curl object. 
     *
     * @return void
     * @param string the url 
     * @param method should be the METHOD_* constants from above
     * @param array any header values to add to the request
     * @param array with username, password, and optionally authtype e.g. CURLAUTH_DIGEST
     */
    private function _setupCurl($url, $method, $header=array(), $auth=array())
    {
        $this->curl = null;

        if (!isset($method)) {
            throw new Exception(
                sprintf("%s: Error, the request method is not set!"));
        }

        if (!is_array($header)) {
            $header = array();
        }

        if (!is_array($auth)) {
            $auth = array();
        }

        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_USERAGENT, RESTClient::USERAGENT);
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        /* curl_setopt($this->curl, CURLOPT_VERBOSE, true);  */

        if ((count($auth) > 0) && isset($auth['username']) && 
            isset($auth['password'])) {
            curl_setopt($this->curl, CURLOPT_HTTPAUTH, 
                isset($auth['authtype']) ? $auth['authtype'] : CURLAUTH_DIGEST);
            curl_setopt(CURLOPT_USERPWD, 
                sprintf("%s:%s", $auth['username'], $auth['password']));
        }


        if ($method == RESTClient::METHOD_PUT) {
            array_push($header, 'Expect:');
        }

        if (count($header) > 0) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $header);
        }

        switch($method) {
        case RESTClient::METHOD_GET:
            curl_setopt($this->curl, CURLOPT_HTTPGET, true);
            break;
        case RESTClient::METHOD_POST:
            curl_setopt($this->curl, CURLOPT_POST, true);
            break;
        case RESTClient::METHOD_PUT:
            curl_setopt($this->curl, CURLOPT_PUT, true);
            break;
        case RESTClient::METHOD_DELETE:
            curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "DELETE");
            break;
        }

    }
}
