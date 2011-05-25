<?php
class SFB_BaseFBAuthController extends SFB_BaseController
{
    
    /**
     * Instance of the Facebook authentication class
     * @var SFB_FacebookAuth;
     */
    protected $_fbAuth;
    /**
     * User's Facebook ID
     * @var bigint
     */
    protected $_fbid;
    /**
     * Has the user installed the application?
     * @var bool
     */
    protected $_hasInstalled;
    /**
     * @var SFB_Facebook Instance of the Facebook access model
     */
    protected $_facebook;
    /**
     * Array of passed facebook signitures
     * @var array
     */
    protected $_fbSigs;
    /**
     * Facebook Settings
     * @var array
     */
    protected $_settings;

    public function init()
    {
        /*
         * Check the valid facbook sigs have been passed
         */
        $this->_fbSigs = Zend_Filter::filterStatic($this->_getParam('signed_request', ''), 'StripTags');        
        if (empty($this->_fbSigs))
        {
            throw new SFB_Exception('No FBSIGS have been passed!');
        }
        
        $this->_settings = $this->getInvokeArg('bootstrap')->getOption('SFB');        
        if(!array_key_exists('fbAppId', $this->_settings) || 
                empty($this->_settings['fbAppId']))
        {
            throw new SFB_Exception('Invalid AppID in application.ini');
        }
        if(!array_key_exists('fbSecret', $this->_settings) || 
                empty($this->_settings['fbSecret']))
        {
            throw new SFB_Exception('Invalid Secret in application.ini');
        }
        
        /*
         * Authenticate the facebook sigs
         */
        $this->_fbAuth = new SFB_FacebookAuth($this->_fbSigs, $this->_settings);
        /*
         * Set the _fbid var to be the fbid if auth was successful
         */
        if($this->_fbAuth->isAuthed && $this->_fbAuth->hasInstalled && $this->_fbAuth->getFBID())
        {
            $this->_fbid = $this->_fbAuth->getFBID();
        }
        else
        {
            $this->_fbid = 0;
        }
        /*
         * set _hasInstalled to be wether the user has installed the app or not
         */
        $this->_hasInstalled = $this->_fbAuth->hasInstalled;

        /*
         * Set up the Facebook class
         */
        $this->_facebook = new SFB_Facebook(
            $this->_settings['fbAppId'],
            $this->_settings['fbSecret'],
            $this->_fbAuth->fbSigs
        );
        
    }

}
?>
