<?php
/**
 * Project: shadowsocks-panel
 * Author: Sendya <18x@loacg.com>
 * Time: 2016/4/16 23:58
 */


namespace Controller\Admin;


use Core\Template;
use Helper\Option;
use Helper\Utils;
use Model\Mail;
use Model\User;
use Helper\Mailer as Mailer1;

/**
 * Class Mailer
 * @Admin
 * @Authorization
 * @package Controller\Admin
 */
class Mailer
{
    private $subject;
    private $content;

    public function index()
    {
        $data['selectMail'] = Option::get('MAIL_AVAILABLE');
        $data['user'] = User::getCurrent();
        Template::setContext($data);
        Template::setView('admin/mailer');
    }

    /**
     * @JSON
     */
    public function test()
    {
        $result = array('error' => 1, 'message' => 'Error sending mail, check the mail profile');
        $user = User::getCurrent();
        $mailer = Mailer1::getInstance();

        $mail = new Mail();
        $mail->to = $user->email;
        $mail->subject = '[' . SITE_NAME . '] This is a test message';
        $mail->content = 'This is <b>Single send</b> Test message';
        $mail->content .= "<p style=\"padding: 1.5em 1em 0; color: #999; font-size: 12px;\">—— This message from the " . SITE_NAME . " (<a href=\"" . BASE_URL . "\">" . BASE_URL . "</a>) Send Administrator</p>";
        if (!$mailer->send($mail)) {
            return $result;
        }
        $mailer->toQueue(true);
        $mail->subject = '[' . SITE_NAME . '] This is more than one item to send a test message';
        $mail->content = 'This is <b>A plurality of transmitting</b> Test message';
        $mail->content .= "<p style=\"padding: 1.5em 1em 0; color: #999; font-size: 12px;\">—— This message from the " . SITE_NAME . " (<a href=\"" . BASE_URL . "\">" . BASE_URL . "</a>) Send Administrator</p>";
        if (!$mailer->send($mail)) {
            return $result;
        } else {
            $result = array('error' => 0, 'message' => 'Message has been sent to your mailbox on the');
            return $result;
        }
    }

    /**
     * @JSON
     */
    public function reset()
    {
        $result = array('error' => 0, 'message' => 'Reset ' . $_POST['mail_type'] . ' Mail configuration item complete');
        Option::delete('Mail_' . $_POST['mail_type']);

        Option::init();
        return $result;
    }

    /**
     * @JSON
     * @return array
     * @throws \Core\Error
     */
    public function postAll()
    {
        $subject = $_POST['mailer_subject'];
        $content = nl2br(htmlspecialchars($_POST['mailer_content']));
        $content .= "<p style=\"padding: 1.5em 1em 0; color: #999; font-size: 12px;\">—— This message from the " . SITE_NAME . " (<a href=\"" . BASE_URL . "\">" . BASE_URL . "</a>) Send Administrator</p>";
        if ($subject == null || $subject == '' || $content == null || $content == '') {
            return array('error' => 1, 'message' => 'Request error, the parameters you submit wrong.');
        }
        $users = User::getUserList();
        $mailer = Mailer1::getInstance();
        $mailer->toQueue(true);

        foreach ($users as $user) {
            $mail = new Mail();
            $params = [
                'nickname' => $user->nickname,
                'email' => $user->email,
                'useTraffic' => Utils::flowAutoShow($user->flow_up + $user->flow_down),
                'transfer' => Utils::flowAutoShow($user->transfer),
                'expireTime' => date('Y-m-d H:i:s', $user->expireTime),
            ];
            $mail->subject = $subject;
            $mail->content = Utils::placeholderReplace($content, $params);
            $mail->to = $user->email;
            $mailer->send($mail);
        }

        return array('error' => 0, 'message' => 'Message queue is working, we will begin at a later time..');
    }

    /**
     * @JSON
     */
    public function saveSetting()
    {
        $type = $_POST['mail_type'];
        $mailType = 'MAIL_' . $type;
        $mailer = self::createMailObject($type);
        if (!$mailer->isAvailable()) {
            $config = Option::get($mailType);
        }
        if (!$config) {
            $config = Option::get($mailType);
        }
        $config = json_decode($config, true);
        $_config = [];
        foreach ($config as $key => $val) {
            $k = $key;
            $v = $val;
            if (strpos($k, 'mailer') === false) {
                $_config[] = ['key' => $k, 'value' => $v];
            }
        }
        return array('error' => 0, 'message' => 'Set message parameters', 'configs' => $_config, 'mailer' => $type);
    }

    /**
     * Mail Update System Settings
     *
     * @JSON
     */
    public function update()
    {
        $result['error'] = 0;
        $result['message'] = 'Save to finish';
        foreach ($_POST as $key => $val) {
            if (!empty($val) && strpos($key, 'mail_') !== false) {
                if (strpos($key, 'mailer') === false) { // Determine whether the mail mailer <- This field is used to set the current message is the class name, this configuration is stored in the database without
                    $k = str_replace('mail_', '', $key);
                    $data[$k] = trim($val);
                }
            }
        }
        if (!empty($_POST['mail_mailer'])) {
            $config = json_encode($data);
            $mailer = trim($_POST['mail_mailer']);
            Option::set('MAIL_' . $mailer, $config);
            Option::set('MAIL_AVAILABLE', $mailer);
        } else {
            $result['error'] = 1;
            $result['message'] = 'Save failed, incomplete parameters';
        }
        Option::init();
        return $result;
    }

    private static function createMailObject($mailClass)
    {
        $class = "\\Helper\\Mailer\\{$mailClass}";
        $mailer = new $class;
        return $mailer;
    }

}