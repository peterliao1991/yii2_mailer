<?php
/**
 * Created by PhpStorm.
 * User: instar
 * Date: 13/07/2018
 * Time: 2:20 PM
 */
namespace peter\mailerqueue;

use Yii;

class MailerQueue extends \yii\swiftmailer\Mailer{

    //修改父类指定的messageclass
    public $messageClass = 'peter\mailerqueue\Message';

    //这两个属性时新加的,这里加了之后,在配置文件中才能进行配置
    public $db = '1';
    public $key = 'mails';


    /**
     * 把存在redis中的邮件取出来进行发送
     * @return bool
     * @throws \yii\base\InvalidConfigException
     */
    public function process(){
        $redis = Yii::$app->redis;
        if(empty($redis)){
            throw new \yii\base\InvalidConfigException('redis not found in config');
        }

        if($redis->select($this->db) && $messages = $redis->lrange($this->key, 0,1)){
            $messageObj = new Message();
            foreach ($messages as $message){
                $message = json_decode($message, true);

                if(empty($message) || !$this->setMessage($messageObj, $message)){
                    throw new \ServerErrorHttpException('message error');
                }
                if($messageObj->send()){
                    $redis->lrem($this->key, -1, json_encode($message));
                }
            }
        }

        return true;
    }


    /**
     * 给消息对象设置消息内容
     * @param $messageObj  消息类对象
     * @param $message  消息内容
     * @return bool
     */
    public function setMessage($messageObj, $message){

        //消息类为空,直接返回false结束
        if(empty($messageObj)){
            return false;
        }

        //如果收件人和发件人为空,直接返回false结束
        if(!empty($message['from']) && !empty($message['to'])){
            $messageObj->setFrom($message['from'])->setTo($message['to']);
            if(!empty($message['cc'])){
                $messageObj->setCc($message['cc']);
            }
            if(!empty($message['bcc'])){
                $messageObj->setBcc($message['bcc']);
            }
            if(!empty($message['reply_to'])){
                $messageObj->setReplyTo($message['reply_to']);
            }
            if(!empty($message['charset'])){
                $messageObj->setCharset($message['charset']);
            }
            if(!empty($message['subject'])){
                $messageObj->setSubject($message['subject']);
            }
            if(!empty($message['html_body'])){
                $messageObj->setHtmlBody($message['html_body']);
            }
            if(!empty($message['text_body'])){
                $messageObj->setTextBody($message['text_body']);
            }

            return $messageObj;
        }

        return false;
    }




}
