<?php

namespace app\behaviors;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

class AvatarBehavior extends Behavior
{
    public $OKAppKey = '***';
    public $OKSecretAppKey = '***';
    public $noImageFile = [
        '/img/no_avatar_m.png',
        '/img/no_avatar_f.png'
    ];

    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'onAfterSave',
            ActiveRecord::EVENT_BEFORE_DELETE => 'onBeforeDelete',
        ];
    }

    public function onAfterSave()
    {
        if ((Yii::$app instanceof \yii\web\Application) && Yii::$app->getRequest()->getIsPost() && $this->owner->social != '') {
            $url = parse_url($this->owner->social);

            if (array_key_exists('host', $url)) {
                switch ($url['host']) {
                    case 'vk.com':
                        $userId = explode('/', $url['path'])[1];
                        $data = [
                            'user_ids' => $userId,
                            'fields' => 'photo_100',
                            'v' => '5.68',
                        ];

                        $curl = curl_init();
                        curl_setopt($curl, CURLOPT_URL, sprintf('%s?%s', 'https://api.vk.com/method/users.get', http_build_query($data)));
                        curl_setopt($curl, CURLOPT_HEADER, 0);
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                        $response = curl_exec($curl);
                        curl_close($curl);

                        $result = json_decode($response, true);
                        $this->saveImage(ArrayHelper::getValue($result, 'response.0.photo_100'));
                        break;
                    case 'ok.ru':
                        $userId = explode('/', $url['path'])[2];
                        $data = [
                            'uids' => $userId,
                            'fields' => 'pic128x128',
                            'method' => 'users.getInfo',
                            'emptyPictures' => true,
                            'format' => 'json',
                            'application_key' => $this->OKAppKey,
                        ];

                        ksort($data);
                        $sig = md5(http_build_query($data, '', '') . $this->OKSecretAppKey);
                        $data += ['sig' => $sig];

                        $curl = curl_init();
                        curl_setopt($curl, CURLOPT_URL, sprintf('%s?%s', 'https://api.ok.ru/fb.do', http_build_query($data)));
                        curl_setopt($curl, CURLOPT_HEADER, 0);
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                        $response = curl_exec($curl);
                        curl_close($curl);

                        $result = json_decode($response, true);
                        $this->saveImage(ArrayHelper::getValue($result, '0.pic128x128'));
                        break;
                    case 'www.facebook.com':
                        $userId = explode('/', $url['path'])[1];
                        $data = [
                            'type' => 'square',
                            'height' => '100',
                        ];

                        $curl = curl_init();
                        curl_setopt($curl, CURLOPT_URL, sprintf('%s/%s/%s?%s', 'https://graph.facebook.com', $userId, 'picture', http_build_query($data)));
                        curl_setopt($curl, CURLOPT_HEADER, 0);
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                        curl_exec($curl);
                        $response = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
                        curl_close($curl);

                        $this->saveImage($response);
                        break;
                }
            }
        }
    }

    public function onBeforeDelete()
    {
        if (!is_null($this->owner->avatar) && file_exists(Yii::getAlias("@webroot/uploads/avatars/" . $this->owner->avatar))) {
            unlink(Yii::getAlias("@webroot/uploads/avatars/" . $this->owner->avatar));
        }
    }

    private function saveImage($path)
    {
        $fileName = uniqid() . '.jpg';

        if ($path && copy($path, Yii::getAlias("@webroot/uploads/avatars/" . $fileName))) {
            $this->owner->avatar = $fileName;
            $this->owner->save();
        }
    }

    public function getAvatarSrc()
    {
        if ($this->hasImage()) {
            return '/uploads/avatars/' . $this->owner->avatar;
        }

        return $this->noImageFile[$this->owner->gender];
    }

    private function hasImage()
    {
        return !is_null($this->owner->avatar)
            && file_exists(Yii::getAlias("@webroot/uploads/avatars/" . $this->owner->avatar));
    }
}
