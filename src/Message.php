<?php
/**
 * Created by PhpStorm.
 * User: instar
 * Date: 13/07/2018
 * Time: 1:53 PM
 */
namespace peter\mailerqueue;
use Yii;

class Message extends \yii\swiftmailer\Message{

    /**
     * 把邮件信息存入redis中
     * @return mixed
     * @throws \yii\base\InvalidConfigException
     */
    public function queue(){
        $redis = Yii::$app->redis;
        if(empty($redis)){
            throw new \yii\base\InvalidConfigException('redis not found in config');
        }

        //reids 0-15个库, 给mailer配置一个db属性
        $mailer = Yii::$app->mailer;

        if(empty($mailer) || !$redis->select($mailer->db)){
            throw new \yii\base\InvalidConfigException('db not found');
        }
        //组装邮件信息
        $message = [];
        $message['from'] = array_keys($this->from);
        $message['to'] = array_keys($this->getTo());
        $message['cc'] =  !empty($this->getCc()) ? array_keys($this->getCc()) : $this->getCc();
        $message['bcc'] = !empty($this->getBcc()) ? array_keys($this->getCc()) : $this->getCc();
        $message['reply_to'] = !empty($this->getReplyTo()) ? array_keys($this->getReplyTo()) : $this->getReplyTo();
        $message['charset'] = !empty($this->getCharset()) ? array_keys($this->getCharset()) : $this->getCharset();
        $message['subject'] = $this->getSubject();

        //获取邮件信息的子信息(就是一封邮件发送出去了,有人回复了邮件,然后发邮件者又再回复)
        $parts = $this->getSwiftMessage()->getChildren();
        //如果没有回复信息,就直接获取邮件信息就可以了
        if(!is_array($parts) || !sizeof($parts)){
            $parts = [$this->getSwiftMessage()];
        }

        foreach($parts as $part){
            //判断是否是附件
            if(!$parts instanceof \Swift_Mime_Attachment){
                switch ($part->getContentType()){
                    case 'text/html':
                        $message['html_body'] = $part->getBody();
                        break;
                    case 'text/palin':
                        $message['text_body'] = $part->getBody();
                        break;
                }
                if(!$message['charset']){
                    $message['charset'] == $part->getCharset();
                }
            }

        }

        //把邮件内容放入到redis中
        return $redis->rpush($mailer->key,json_encode($message));

    }

}
