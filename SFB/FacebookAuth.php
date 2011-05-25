<?php
class SFB_FacebookAuth
{
    /**
     * User's Facebook ID
     * @var biging
     */
    private $_fbid;
    /**
     * Passed Facebook sigs
     * @var array
     */
    private $_fbSigs;
    /**
     * Facebook Settings
     * @var array
     */
    private $_fbSettings;
    /**
     * Decoded Facebook parameters
     * @var array
     */
    public $fbSigs;
    /**
     * Has the user sucessfully authenticated?
     * @var bool
     */
    public $isAuthed = false;
    /**
     * Has the user installed the application?
     * @var bool
     */
    public $hasInstalled = false;
    
    
    /**
     * Contructor
     * @param string $fbSigs "signed_request" parameter passed by Facebook
     * @param array $settings Array of Facebook settings
     */
    public function __construct($fbSigs, $settings)
    {
        $this->_fbSettings = $settings;
        $this->_fbSigs = $fbSigs;
        $this->_parseSignedRequest();
    }

    public function getFBID()
    {
        return $this->_fbid;
    }

    public function getFBSigs()
    {
        return $this->_fbSigs;
    }

    private function _parseSignedRequest()
    {
        if (!is_string($this->_fbSigs) || empty($this->_fbSigs))
        {
            $this->isAuthed = false;
            throw new SFB_Exception('Invalid Sigs');
        }
        list($encoded_sig, $payload) = explode('.', $this->_fbSigs, 2);

        // decode the data
        $sig = $this->_base64UrlDecode($encoded_sig);
        $data = json_decode($this->_base64UrlDecode($payload), true);
        if (strtoupper($data['algorithm']) !== 'HMAC-SHA256')
        {
            $this->isAuthed = false;
            throw new SFB_Exception('Invalid Sigs');
        }

        // check sig
        $expected_sig = hash_hmac('sha256', $payload, $this->_fbSettings['fbSecret'], $raw = true);
        if ($sig !== $expected_sig) 
        {
            $this->isAuthed = false;
            throw new SFB_Exception('Invalid Sigs');
        }

        $this->isAuthed = true;
        $this->fbSigs = $data;

        if (isset($this->fbSigs['oauth_token']) 
            && is_string($this->fbSigs['oauth_token']) 
            && !empty($this->fbSigs['oauth_token']))
        {
            $this->hasInstalled = true;
        }
        if (isset($this->fbSigs['user_id']) 
            && is_string($this->fbSigs['user_id']) 
            && !empty($this->fbSigs['user_id']))
        {
            $this->_fbid = $this->fbSigs['user_id'];
        }
        else
        {
            $this->_fbid = '0';
            $this->isAuthed = false;
        }
        return true;
    }

    private function _base64UrlDecode($input)
    {
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
?>
