<?php
class ReCaptcha extends CInputWidget
{

    const JS_API_URL  = 'https://www.google.com/recaptcha/api.js';
    const THEME_LIGHT = 'light';
    const THEME_DARK  = 'dark';
    const TYPE_IMAGE  = 'image';
    const TYPE_AUDIO  = 'audio';

    /** @var string Your sitekey. */
    public $key;

    /** @var string Your secret. */
    public $secret;

    /** @var string The color theme of the widget. [[THEME_LIGHT]] (default) or [[THEME_DARK]] */
    public $theme;

    /** @var string The type of CAPTCHA to serve. [[TYPE_IMAGE]] (default) or [[TYPE_AUDIO]] */
    public $type;

    /** @var string Your JS callback function that's executed when the user submits a successful CAPTCHA response. */
    public $jsCallback;

    /** @var array Additional html widget options, such as `class`. */
    public $widgetOptions = array();

    /** @var if you might have a large number of hosted domains and would like to have one key working on all of them - the solution is the secure token. */
    public $isSecureToken = false;

    public function init()
    {
        parent::init();
        if (empty($this->key)) {
            if (!empty(Yii::app()->reCaptcha->key)) {
                $this->key = Yii::app()->reCaptcha->key;
            } else {
                throw new CException('Required `siteKey` param isn\'t set.');
            }
        }

        if (empty($this->secret)) {
            if (!empty(Yii::app()->reCaptcha->secret)) {
                $this->secret = Yii::app()->reCaptcha->secret;
            } else {
                throw new CException('Required `secret` param isn\'t set.');
            }
        }

        $cs = Yii::app()->getClientScript();
        $cs->registerScriptFile(self::JS_API_URL . '?hl=' . $this->getLanguageSuffix());
    }

    public function run()
    {
        $this->customFieldPrepare();
        $divOptions = array(
            'class'        => 'g-recaptcha',
            'data-sitekey' => $this->key
        );

        if (!empty($this->jsCallback)) {
            $divOptions['data-callback'] = $this->jsCallback;
        }

        if (!empty($this->theme)) {
            $divOptions['data-theme'] = $this->theme;
        }

        if (!empty($this->type)) {
            $divOptions['data-type'] = $this->type;
        }

        if (isset($this->widgetOptions['class'])) {
            $divOptions['class'] = "{$divOptions['class']} {$this->widgetOptions['class']}";
        }

        if ($this->isSecureToken) {
            $sessionId   = Yii::app()->getSession()->getSessionId();
            $secureToken = $this->createSecureToken($sessionId);

            $divOptions['data-stoken'] = $secureToken;
        }


        $divOptions = $divOptions + $this->widgetOptions;
        echo CHtml::tag('div', $divOptions, '');
    }

    protected function getLanguageSuffix()
    {
        $currentAppLanguage = Yii::app()->language;
        $langsExceptions    = array('zh-CN', 'zh-TW', 'zh-TW');
        if (strpos($currentAppLanguage, '-') === false) {
            return $currentAppLanguage;
        }
        if (in_array($currentAppLanguage, $langsExceptions)) {
            return $currentAppLanguage;
        } else {
            return substr($currentAppLanguage, 0, strpos($currentAppLanguage, '-'));
        }
    }

    protected function customFieldPrepare()
    {
        if ($this->hasModel()) {
            $inputName = CHtml::resolveName($this->model, $this->attribute);
            $inputId   = CHtml::getIdByName(CHtml::resolveName($this->model, $this->attribute));
        } else {
            $inputName = $this->name;
            $inputId   = 'recaptcha-' . $this->name;
        }

        if (empty($this->jsCallback)) {
            $jsCode = "var recaptchaCallback = function(response){jQuery('#{$inputId}').val(response);};";
        } else {
            $jsCode = "var recaptchaCallback = function(response){jQuery('#{$inputId}').val(response); {$this->jsCallback}(response);};";
        }
        $this->jsCallback = 'recaptchaCallback';
        $cs               = Yii::app()->getClientScript();
        $cs->registerScript('recaptcha-js', $jsCode, CClientScript::POS_BEGIN);
        echo CHtml::hiddenField($inputName, null, array('id' => $inputId));
    }

    /**
     * Create an encrypted secure token for the given session id.
     *
     * @see https://developers.google.com/recaptcha/docs/secure_token
     *
     * @param string $session_id a unique session identifier.
     * @param int|null $timestamp in milliseconds, defaults to current time.
     * @return string Recaptcha-compatible base64 encoded encrypted binary data.
     */
    public function createSecureToken($session_id, $timestamp = null)
    {
        if (is_null($timestamp)) {
            $timestamp = $this->currentTimestamp();
        }
        $params    = array('session_id' => $session_id, 'ts_ms' => $timestamp);
        $plaintext = json_encode($params);
        $encrypted = $this->encryptData($plaintext);
        return $this->base64Encode($encrypted);
    }

    /**
     * Decode and decrypt a secure token generated using this algorithm.
     *
     * @param string $secure_token base64 encoded secure token
     * @return array includes the keys 'session_id' and 'ts_ms'
     */
    public function decodeSecureToken($secure_token)
    {
        $binary    = $this->base64Decode($secure_token);
        $decrypted = $this->decryptData($binary);
        return json_decode($decrypted);
    }

    /**
     * Encrypt an arbitrary string using the site secret.
     *
     * @param string $plaintext
     * @return string binary data
     */
    public function encryptData($plaintext)
    {
        $padded     = $this->pad($plaintext, 16);
        $siteSecret = $this->secretKey();
        ;
        return $this->encryptAes($padded, $siteSecret);
    }

    /**
     * Decrypt the given data using the site secret.
     *
     * @param string $encrypted binary data
     * @return string plaintext string
     */
    public function decryptData($encrypted)
    {
        $site_secret = $this->secretKey();
        $padded      = $this->decryptAes($encrypted, $site_secret);
        return $this->stripPadding($padded);
    }

    /**
     * Get the current timestamp in milliseconds.
     *
     * @return int
     */
    protected function currentTimestamp()
    {
        return doubleval(round(microtime(true) * 1000));
    }

    /**
     * Returns the site secret in the key format required for encryption.
     *
     * @return string
     */
    protected function secretKey()
    {
        if (!isset($this->secret)){
            throw new \BadMethodCallException("Missing site_secret");
        }
        $secretHash = hash('sha1', $this->secret, true);
        return substr($secretHash, 0, 16);
    }

    /**
     * Encrypts the given input string using the provided key.
     *
     * Note that the algorithm, block mode, and key format
     * are defined by ReCaptcha code linked below.
     *
     * @see https://github.com/google/recaptcha-java/blob/master/appengine/src/main/java/com/google/recaptcha/STokenUtils.java
     *
     * @param $input
     * @param $secret
     *
     * @return string
     */
    protected function encryptAes($input, $secret)
    {
        return mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $secret, $input, MCRYPT_MODE_ECB);
    }

    /**
     * @param $input
     * @param $secret
     *
     * @return string
     */
    protected function decryptAes($input, $secret)
    {
        return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $secret, $input, MCRYPT_MODE_ECB);
    }

    /**
     * Pad the input string to a multiple of {$block_size}. The
     * padding algorithm is defined in the PKCS#5 and PKCS#7 standards
     * (which differ only in block size). See RFC 5652 Sec 6.3 for
     * implementation details.
     *
     * NB: the Java implementation of the ReCaptcha encryption algorithm
     * uses a block size of 16, despite being named PKCS#5. This is
     * consistent with the AES 128-bit cipher.
     *
     * @param string $input
     * @param int $block_size
     * @return string
     */
    protected function pad($input, $block_size = 16)
    {
        $pad = $block_size - (strlen($input) % $block_size);
        return $input . str_repeat(chr($pad), $pad);
    }

    /**
     * Naively strip padding from an input string.
     *
     * @param string $input padded input string.
     * @return string
     */
    protected function stripPadding($input)
    {
        $padding_length = ord(substr($input, -1));
        return substr($input, 0, strlen($input) - $padding_length);
    }

    /**
     * Generate an "URL-safe" base64 encoded string from the
     * given input data.
     *
     * @param string $input
     * @return string
     */
    protected function base64Encode($input)
    {
        return str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($input));
    }

    /**
     * Decode an "URL-safe" base64 encoded string.
     *
     * @param string $input
     * @return string
     */
    protected function base64Decode($input)
    {
        return base64_decode(str_replace(array('-', '_'), array('+', '/'), $input));
    }

}
