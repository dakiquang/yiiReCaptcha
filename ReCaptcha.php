<?php

/**
 * @link https://github.com/dakiquang/yiiReCaptcha
 * @copyright Copyright (c) 2015 CeresSolutions
 * @license http://opensource.org/licenses/MIT MIT
 */

/**
 * Recaptcha class file
 * Google reCAPTCHA widget for Yii 1
 *
 * @author QUANG Dang <dkquang@ceresolutions.com>
 * @link http://ceresolutions.com/
 * @copyright 2015 Ceres Solutions LLC
 */
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
    public $widgetOptions = [];

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
        $divOptions = $divOptions + $this->widgetOptions;
        echo CHtml::tag('div', $divOptions, '');
    }

    protected function getLanguageSuffix()
    {
        $currentAppLanguage = Yii::app()->language;
        $langsExceptions    = ['zh-CN', 'zh-TW', 'zh-TW'];
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

}
