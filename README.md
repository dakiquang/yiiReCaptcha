YiiReCaptcha 
============
Based on reCaptcha API 2.0

## Quick Start

You can clone the [github repo](https://github.com/dakiquang/yiiReCaptcha) and get the full codebase to build the distributive something you want. 

## Installation
* 1/ Download GitHub repo (dakiquang/yiiReCaptcha) and extract files into a destination folder(extensions folder/vendor folder or any folder in your structure)

* 2/ [Sign up for an reCAPTCHA API keys on Google reCaptcha](https://www.google.com/recaptcha/admin#createsite). and get the key/secret pair

* 3/ Configure this component in your configuration file (main.php file). The parameters siteKey and secret are required.

```php
'components' => [
    'reCaptcha' => [
        'name' => 'reCaptcha',
        'class' => '<path-to-destination-folder>\yiiReCaptcha\ReCaptcha',
        'key' => '<your-key>',
        'secret' => '<your-secret>',
    ],
    ...
```

4/ Add `ReCaptchaValidator` in your model, for example:
```php
    public $verifyCode;

    public function rules()
    {
        return array(
            array('verifyCode', 'required'),
            array('verifyCode', '<path-to-destination-folder>.yiiReCaptcha.ReCaptchaValidator'),
        );
    }
```

5/ Usage this widget in your view
```php
<?php
$this->widget('<path-to-destination-folder>.yiiReCaptcha.ReCaptcha', array(
    'model'     => $model,
    'attribute' => 'verifyCode',
));
?>
```
6/ Use for multiple domain: By default, the reCaptcha is restricted to the specified domain. Use the secure token to request a CAPTCHA challenge from any domain. Adding more attribute `'isSecureToken' => true` to setup for any domain:
```php
<?php
$this->widget('<path-to-destination-folder>.yiiReCaptcha.ReCaptcha', array(
    'model'     => $model,
    'attribute' => 'verifyCode',
    'isSecureToken' => true,
));
?>
```
END.
