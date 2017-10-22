<?php

/**
 * @link https://github.com/dakiquang/yiiReCaptcha
 * @copyright Copyright (c) 2015 CeresSolutions
 * @license http://opensource.org/licenses/MIT MIT
 */

/**
 * ReCaptchaValidator class file
 * Description of Recaptcha
 *
 * @author QUANG Dang <dkquang@ceresolutions.com>
 * @link http://ceresolutions.com/
 * @copyright 2015 Ceres Solutions LLC
 */
class ReCaptchaValidator extends CValidator
{

    const SITE_VERIFY_URL        = 'https://www.google.com/recaptcha/api/siteverify';
    const CAPTCHA_RESPONSE_FIELD = 'g-recaptcha-response';

    public $secret;

    /**
     * Constructor (CValidator does not have an init() function)
     */
    public function __construct()
    {
        // note: validator has no parent::__construct()
        $this->init();
    }

    public function init()
    {
        if (empty($this->secret)) {
            if (!empty(Yii::app()->reCaptcha->secret)) {
                $this->secret = Yii::app()->reCaptcha->secret;
            } else {
                throw new InvalidConfigException('Required `secret` param isn\'t set.');
            }
        }
        if ($this->message === null || empty($this->message)) {
            $this->message = Yii::t('yii', 'An error occurred when attempting to verify your reCAPTCHA.');
        }
    }

    /**
     * Validate recaptcha
     * @param CModel $object the data object being validated
     * @param string $attribute the name of the attribute to be validated.
     * @return mixed
     * @throws CException
     */
    protected function validateAttribute($object, $attribute)
    {
        // get input value
        $value = $object->$attribute;
        if (empty($value)) {
            if (!($value = Yii::app()->request->getParam(self::CAPTCHA_RESPONSE_FIELD))) {
                $message = $this->message;
                $this->addError($object, $attribute, $message);
                return;
            }
        }
        
        // Make request
        $request  = self::SITE_VERIFY_URL . '?' . http_build_query(
            array(
                'secret'   => $this->secret,
                'response' => $value,
                'remoteip' => Yii::app()->request->getUserHostAddress()
            )
        );
        $response = $this->getResponse($request);
        
        // Errors
        if (!isset($response['success'])) {
            throw new CException('Invalid recaptcha verify response.');
        }
        if (!$response['success']) {
            $message = Yii::t('yii', 'reCAPTCHA was not able to verify you.');
            // Add error codes from API response to validation error message.
            if (Yii::app()->reCaptcha->showResponseErrors){
                if (isset($response['error-codes']) && is_array($response['error-codes'])){
                    $message .= ' Error code(s): ';
                    $message .= implode(',', $response['error-codes']);
                }
            }
            $this->addError($object, $attribute, $message);
        }
    }

    /**
     * 
     * Validate recaptcha
     * @param CModel $object the data object being validated
     * @param string $attribute the name of the attribute to be validated.
     * @return string
     */
    public function clientValidateAttribute($object, $attribute)
    {
        $message = Yii::t(
                'yii', '{attribute} cannot be blank.', array('attribute' => $object->getAttributeLabel($attribute))
        );
        return "(function(messages){if(!grecaptcha.getResponse()){messages.push('{$message}');}})(messages);";
    }

    /**
     * @param string $request
     * @return mixed
     */
    protected function getResponse($request)
    {
        $response = file_get_contents($request);
        return CJSON::decode($response, true);
    }

}
