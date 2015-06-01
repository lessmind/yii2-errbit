<?php

namespace nkovacs\errbit;

use Yii;
use yii\helpers\Json;
use yii\helpers\Url;

/**
 * WebErrorHandler sends errors to errbit.
 */
class WebErrorHandler extends \yii\web\ErrorHandler
{
    /**
     * @var boolean whether to enable javascript error notifier
     */
    public $jsNotifier = false;

    /**
     * @var array additional js options
     * Keys are option names, e.g. currentUser, which will call Airbrake.setCurrentUser
     */
    public $jsOptions = [];

    use ErrorHandlerTrait {
        ErrorHandlerTrait::register as traitRegister;
    }

    public function register()
    {
        $this->traitRegister();
        if ($this->jsNotifier) {
            Yii::$app->on(\yii\web\Application::EVENT_BEFORE_ACTION, function ($event) {
                Yii::$app->view->on(\yii\web\View::EVENT_BEFORE_RENDER, function ($event) {
                    $host = $this->errbit['host'];
                    if (Url::isRelative($host)) {
                        $host = '//' . $host;
                    }
                    $event->sender->registerJsFile(rtrim($host, '/') . '/javascripts/notifier.js', [
                        'position' => \yii\web\View::POS_HEAD,
                    ]);

                    $js = 'Airbrake.setKey(' . Json::htmlEncode($this->errbit['api_key']) . ');';
                    $js .= 'Airbrake.setHost(' . Json::htmlEncode($this->errbit['host']) . ');';
                    if (isset($this->errbit['environment_name'])) {
                        $js .= 'Airbrake.setEnvironment(' . Json::htmlEncode($this->errbit['environment_name']) . ');';
                    }

                    if (is_array($this->jsOptions)) {
                        foreach ($this->jsOptions as $key => $value) {
                            $js .= 'Airbrake.set' . ucfirst($key) . '(' . Json::htmlEncode($value) . ');';
                        }
                    }

                    $controller = Yii::$app->controller;
                    if ($controller !== null && $controller instanceof UserInfoInterface) {
                        $user = $controller->getErrbitUserInfo();
                        if (is_array($user)) {
                            $js .= 'Airbrake.setCurrentUser(' . Json::htmlEncode($user) . ');';
                        }
                    }

                    $event->sender->registerJs(
                        $js,
                        \yii\web\View::POS_HEAD
                    );
                });
            });
        }
    }
}
