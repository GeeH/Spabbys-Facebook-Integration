<?php

class SFB_Facebook extends SFB_Base
{    
    private $_AppID;
    private $_Secret;
    private $_Sigs;
    private $_Session;

    public $UseCache = true;

    const GRAPHURL = 'https://graph.facebook.com/';
    const FQLURL = 'https://api.facebook.com/method/fql.query';

    public function  __construct($AppID='', $Secret='', array $Sigs=array())
    {
        if (empty($AppID) || empty($Secret) || empty($Sigs)) {
            if ( APPLICATION_ENV != 'production' && APPLICATION_ENV != 'ggfproduction') {
               throw new Exception("Error instanciating Facebook class appid:$AppID sigs:$Sigs");
            } else {
               throw new Exception('Error instanciating Facebook class');
            }
        }
        $this->_AppID = $AppID;
        $this->_Secret = $Secret;
        $this->_Sigs = $Sigs;
        $this->_Session  = new Zend_Session_Namespace('Facebook');
        ini_set('precision','24');
    }

    /**
     * Clears the entire Facebook cache
     */
    public function clearCache()
    {
        $this->_Session->unsetAll();
    }

    /**
     * Grabs logged in user info from the Graph
     * @param bool $resetCache Empty any data in the session cache?
     * @return array
     */
    public function getUserInfo($fbid=null, $resetCache=false, $checkSigs=true)
    {
        if (is_null($fbid))
        {
            $url = self::GRAPHURL.'me/';
        }
        else
        {
            $url = self::GRAPHURL.$fbid.'/';
        }
        $data = $this->_getFromGraph($url, array(), !$resetCache, $checkSigs);
        return (array) $data;
    }

    /**
     * Grabs users friends from the Graph
     * @param string $fbid The Facebook ID of the user
     * @param bool $onlyPlaying Only return friends who are using the app?
     * @param bool $resetCache Empty any data in the session cache?
     * @return array
     */
    public function getUserFriends($fbid ,$onlyPlaying=false, $resetCache=false, $notPlaying=false)
    {
        $fql = "SELECT uid, has_added_app FROM user WHERE uid IN (SELECT uid2 FROM friend WHERE uid1 = $fbid)";
        $data = $this->_doFQLQuery( $fql, $resetCache );
        $return = array();
        if ( !empty( $data ) && is_array($data)  ) {
            foreach ($data as $d) {
                if((!$onlyPlaying && !$notPlaying) || ($onlyPlaying && $d->has_added_app) ||
                        (!$onlyPlaying && $notPlaying && !$d->has_added_app))
                {
                    $return[] = (string) Zend_Filter::filterStatic($d->uid, 'Digits');
                }
            }
        }
        return (array) $return;
    }

    /**
     * Checks wether logged in user has given permission
     * @param string $fbid FBID of user to check
     * @param string $perm Permission to check
     * @param bool $resetCache Empty any data in the session cache?
     * @return bool
     */
    public function getUserHasPerm($fbid, $perm, $resetCache=false)
    {
        $fql = "SELECT $perm FROM permissions WHERE uid=$fbid";
        $data = $this->_doFQLQuery($fql, $resetCache);        
        if(empty($data))
        {
            return false;
        }
        if (isset($data[0]->{$perm}))
        {
            return $data[0]->{$perm} == 1;
        }
        else
        {
            $logfile = APPLICATION_PATH.$this->_settings->loggingBase.'Facebook'.date('dmY').'.txt';
            $writer = new Zend_Log_Writer_Stream($logfile);
            $logger = new Zend_Log($writer);
            $logger->log('getUserHasPerm: '.json_encode($data));
            /**
             * TEMP REMOVE PLEASE
             */
            if(APPLICATION_ENV != 'production')
            {
                echo("There has been an error, please tell Gary/Mark and paste this data");
                pr($data);
            }
            return false;
        }
    }

    /**
     * Checks if a given user "likes" a given page
     * @param string $fbid The user's Facebook ID
     * @param string $pageid The Page ID of the page
     * @param bool $resetCache Empty any data in the session cache?
     * @return bool
     */
    public function getUserLikes($fbid, $pageid, $resetCache=false)
    {
        $fql = "SELECT uid FROM page_fan WHERE page_id=\"$pageid\" and uid=\"$fbid\"";
        $data = $this->_doFQLQuery($fql, $resetCache);
        if ( !is_array($data) )
        {
            return false;
        }
        return true;
    }

    /**
     * Checks if the expirey time of sigs has passed
     * @todo Add some form of renewal of sigs if the exirey has passed
     * @return bool
     */
    public function checkSigs()
    {
        if (!isset($this->_Sigs['expires']) || time() > $this->_Sigs['expires'])
            return false;
        return true;
    }

    /**
     * Handles requests to the Facebook Graph API
     * @param string $url The URL to query
     * @param array $args An array of arguement to pass
     * @param bool $useCache Use the session caching?
     * @param bool $checkSigs Check the sigs first?
     * @return object
     */
    protected function _getFromGraph($url, array $args=array(), $useCache=true, $checkSigs=true)
    {
        if ( ( $url == self::FQLURL ) && $useCache ) throw new Exception('FQL queries cannot be cached by this method, use _doFQLQuery()') ;
        if (!$this->UseCache || !$useCache || ($this->UseCache && $useCache && !$this->_Session->__isset($url)))
        {
            if($this->checkSigs() || !$checkSigs)
            {
                $http = new Zend_Http_Client($url);
                $args['access_token']=$this->_Sigs['oauth_token'];                
                $http->setParameterGet($args);
                try {
                    $request = $http->request();
                    if($request->getStatus() != 200)
                    {
                        return array();
                    }
                    else
                    {
                        $return = json_decode($request->getBody());
                        if ($this->UseCache && $useCache )
                        {
                            $this->_Session->{$url} = $return;
                        }
                        return $return;
                    }
                }
                catch (Exception $e)
                {
                    return array();
                }

            }
            else
            {
                return array();
            }
        }
        else
        {
            return $this->_Session->{$url};
        }
    }

    protected function _postToGraph($url, array $args = array())
    {
        if($this->checkSigs())
        {
            $http = new Zend_Http_Client($url);
            $args['access_token']=$this->_Sigs['oauth_token'];
            $http->setParameterPost($args);
            try {
                $request = $http->request('POST');
                if($request->getStatus() != 200)
                {
                    return array();
                }
                else
                {
                    $return = json_decode($request->getBody());
                    if ($this->UseCache)
                    {
                        $this->_Session->{$url} = $return;
                    }
                    return $return;
                }
            }
            catch (Exception $e)
            {
                return array();
            }

        }
        else
        {
            return array();
        }
    }

    /**
     * Runs an FQL query
     * @param string $fql query to run
     * @param bool $resetCache empty cached query if exists 
     * @return array
     */
    protected function _doFQLQuery($fql, $resetCache=false) {
        if($this->checkSigs())
        {
            if($resetCache || !$this->_Session->__isset($fql))
            {
                $url = self::FQLURL;
                $args = array(
                    'query' => $fql,
                    'format' => 'json'
                );
                $data = $this->_getFromGraph($url, $args, false );
                if ( !$resetCache ) {
                    $this->_Session->{$fql} = $data;
                }
            }
            else
            {
                $data = $this->_Session->{$fql};
            }
            return $data;
        }
        else
        {
            return array();
        }

    }
}