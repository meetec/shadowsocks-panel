<?php
/**
 * SS-Panel
 * A simple Shadowsocks management system
 * Author: Sendya <18x@loacg.com>
 */
namespace Controller;

use Helper\Logger;
use Helper\Mailer;
use Helper\Stats;
use Model\Invite;
use Model\Mail;
use Model\User;
use Model\Message as MessageModel;

use Core\Template;
use Helper\Utils;
use Helper\Option;
use Helper\Encrypt;

class Auth
{

    public function index()
    {
        header("Location: /auth/login");
    }

    /**
     * Login
     *
     * @JSON
     */
    public function login()
    {
         /**
         * 1. Determining if user is logged,
         *      If you have already logged in, go directly to the control panel (dashboard).
         * 2. Loading login page template, enter the login page.
         */
        $user = User::getCurrent();
        if ($user->uid) {
            header("Location:/member");
        } else {
            if (isset($_REQUEST['email']) && isset($_REQUEST['passwd'])) {
                $result = array('error' => 1, 'message' => 'Account does not exist!');
                $email = htmlspecialchars(trim($_REQUEST['email']));
                $passwd = htmlspecialchars(trim($_REQUEST['passwd']));
                $remember_me = htmlspecialchars(trim($_REQUEST['remember_me']));

                $user = User::getUserByEmail($email);

                if ($user) {
                    if ($user->verifyPassword($passwd)) {
                        $result['error'] = 0;
                        $result['message'] = 'Login is successful, will jump to &gt;dashboard';

                        $remember_me == 'week' ? $ext = 3600 * 24 * 7 : $ext = 3600;
                        $expire = time() + $ext;
                        $token = md5($user->uid . ":" . $user->email . ":" . $user->passwd . ":" . $expire . ":" . COOKIE_KEY);
                        setcookie("uid", base64_encode(Encrypt::encode($user->uid, ENCRYPT_KEY)), $expire, "/");
                        setcookie("expire", base64_encode(Encrypt::encode($expire, ENCRYPT_KEY)), $expire, "/");
                        setcookie("token", base64_encode(Encrypt::encode($token, ENCRYPT_KEY)), $expire, "/");

                        $_SESSION['currentUser'] = $user;
                        Logger::getInstance()->info('user [' . $user->email . '] Login success');
                     } else {
                        $result['message'] = "Account name or password is incorrect, please check and try again!";
                        Logger::getInstance()->info('user [' . $user->email . '] Login failed! wrong password');
                    }
                }

                return $result;
            } else {
                $data['globalMessage'] = MessageModel::getGlobalMessage();
                Template::setContext($data);
                Template::setView('panel/login');
            }
        }
    }

    /**
     * Lock screen
     * @JSON
     */
    public function lockScreen()
    {
        // TODO -- This functionality may be deprecated
        // 2016-04-09
    }

    public function logout()
    {
        Logger::getInstance()->info('user [' . User::getCurrent()->email . '] Logout');
        setcookie("uid", '', time() - 3600, "/");
        setcookie("expire", '', time() - 3600, "/");
        setcookie("token", '', time() - 3600, "/");
        $_SESSION['currentUser'] = null;
        header("Location:/");
    }

    /**
     * @JSON
     */
    public function register()
    {
        $result = array('error' => 1, 'message' => 'Registration Failed');
        $email = strtolower(trim($_POST['r_email']));
        $userName = trim($_POST['r_user_name']);
        $passwd = trim($_POST['r_passwd']);
        $repasswd = trim($_POST['r_passwd2']);
        $inviteCode = trim($_POST['r_invite']);
        $invite = Invite::getInviteByInviteCode($inviteCode); //Check invite availability

        if ($invite->status != 0 || $invite == null || empty($invite)) {
            $result['message'] = 'Invitation code is unavailable';
        } else {
            if ($repasswd != $passwd) {
                $result['message'] = 'Enter the password twice inconsistent';
            } else {
               if (strlen($passwd) < 6) {
                    $result['message'] = 'Password is too short, at least 8 characters';
                } else {
                    if ($chkEmail = Utils::mailCheck($email)) {
                        $result['message'] = $chkEmail;
                    } else {
                        $user = new User();
                        $user->email = $email;
                        if ($userName == null) // If the user name is not filled in, the user name when using email
                        {
                            $userName = $email;
                        }
                        $userCount = Stats::countUser();

                        $user->nickname = $userName;

                        // LEVEL Being from the database
                        $custom_transfer_level = json_decode(Option::get('custom_transfer_level'), true);

                        // Packages and invitation code defined flow units
                        $transferNew = Utils::GB * intval($custom_transfer_level[$invite->plan]);

                        $user->transfer = $transferNew;
                        $user->invite = $inviteCode;
                        $user->plan = $invite->plan;// The type of account invitation code to set the registered users.
                        $user->regDateLine = time();
                        $user->lastConnTime = $user->regDateLine;
                        $user->sspwd = Utils::randomChar();
                        $user->payTime = time(); // Paid time registration
                        $user_test_day = Option::get('user_test_day') ?: 7;
                        $user->expireTime = time() + (3600 * 24 * intval($user_test_day)); // Paid time registration

                        $mailVerify = Option::get('mail_verify'); // Verify that the mail open

                        if ($userCount > 0 && $mailVerify) {
                            $user->enable = 0; // Stop Account
                            $code = Utils::randomChar(10);
                            $forgePwdCode['verification'] = $code;
                            $forgePwdCode['time'] = time();
                            $user->forgePwdCode = json_encode($forgePwdCode);

                            $mailer = Mailer::getInstance();
                            $mailer->toQueue(false);
                            $mail = new Mail();
                            $mail->to = $user->email;
                            $mail->subject = '[' . SITE_NAME . '] New Account Registration Mailbox check';
                            $mail->content = Option::get('custom_mail_verification_content');
                            $params = [
                                'code' => $code,
                                'nickname' => $user->nickname,
                                'email' => $user->email,
                                'useTraffic' => Utils::flowAutoShow($user->flow_up + $user->flow_down),
                                'transfer' => Utils::flowAutoShow($user->transfer),
                                'expireTime' => date('Y-m-d H:i:s', $user->expireTime),
                                'REGISTER_URL' => base64_encode($user->email . "\t" . $forgePwdCode['verification'] . "\t" . $forgePwdCode['time'])
                            ];
                            $mail->content = Utils::placeholderReplace($mail->content, $params);
                            $mailer->send($mail);
                        } else {
                            $user->enable = 1; // The first account, the default is set to start
                            $user->forgePwdCode = null;
                        }

                        $user->port = Utils::getNewPort(); // The port number
                        $user->setPassword($passwd);
                        $user->save();

                        $invite->reguid = $user->uid;
                        $invite->regDateLine = $user->regDateLine;
                        $invite->status = 1; // -1 expored 0-not used 1-used
                        $invite->inviteIp = Utils::getUserIP();
                        $invite->save();

                        if (null != $user->uid && 0 != $user->uid) {
                            $result['error'] = 0;
                            $result['message'] = 'Registration successful';
                        }
                        if ($mailVerify) {
                            $result['message'] .= 'In order to use this site, you need to verify the email.';
                        }
                        Logger::getInstance()->info('user [' . $user->email . '] register success');
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 重发校验码
     * @JSON
     * @Authorization
     */
    public function resend()
    {
        if ($_POST['auth'] == 'y') {
            $user = User::getCurrent();

            $code = Utils::randomChar(10);
            $forgePwdCode['verification'] = $code;
            $forgePwdCode['time'] = time();
            $user->forgePwdCode = json_encode($forgePwdCode);

            $mailer = Mailer::getInstance();
            $mailer->toQueue(false);
            $mail = new Mail();
            $mail->to = $user->email;
            $mail->subject = '[' . SITE_NAME . '] New Account Registration Mailbox check';
            $mail->content = Option::get('custom_mail_verification_content');
            $params = [
                'code' => $code,
                'nickname' => $user->nickname,
                'email' => $user->email,
                'useTraffic' => Utils::flowAutoShow($user->flow_up + $user->flow_down),
                'transfer' => Utils::flowAutoShow($user->transfer),
                'expireTime' => date('Y-m-d H:i:s', $user->expireTime),
                'REGISTER_URL' => base64_encode($user->email . "\t" . $forgePwdCode['verification'] . "\t" . $forgePwdCode['time'])
            ];
            $mail->content = Utils::placeholderReplace($mail->content, $params);
            $mailer->send($mail);
            $user->save();
            Logger::getInstance()->info('user [' . $user->email . '] find password, code ' . $code);
        }
        return array('error' => 0, 'message' => 'Resend the message successfully.');
    }

    /**
     * 校验
     *
     */
    public function verification()
    {
        if ($_GET['verification'] != null) {
            $list = explode("\t", base64_decode($_GET['verification']));

            if (count($list) > 2) {
                $user = User::getUserByEmail($list[0]);
                $verification = trim($list[1]);
                $json = json_decode($user->forgePwdCode, true);
                $userVerificationCode = $json['verification'];
                $verifyTime = intval($json['time']);
                $baseURL = BASE_URL . 'auth/login';
                if ($userVerificationCode == $verification && ($verifyTime + 1800) > time()) {

                    $mailer = Mailer::getInstance();
                    $mailer->toQueue(true, true);
                    $mail = new Mail();
                    $mail->to = $user->email;
                    $mail->subject = '[' . SITE_NAME . '] Account registration and verification is successful notification';
                    $mail->content = Option::get('custom_mail_register_content');
                    $params = [
                        'nickname' => $user->nickname,
                        'email' => $user->email,
                        'useTraffic' => Utils::flowAutoShow($user->flow_up + $user->flow_down),
                        'transfer' => Utils::flowAutoShow($user->transfer),
                        'expireTime' => date('Y-m-d H:i:s', $user->expireTime),
                    ];
                    $mail->content = Utils::placeholderReplace($mail->content, $params);
                    $mailer->send($mail);

                    $user->enable = 1;
                    $user->forgePwdCode = null;
                    $user->save();

                    $html = <<<EOF
<!DOCTYPE html>
<html lang="zh-cmn-Hans">
<head>

<meta charset="utf-8">
<title>E-mail verification is successful - Account Registration</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="HandheldFriendly" content="true">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />

</head>
<body>
<p>Verification is successful, Thank you for registering. You can now use all the services of the site.</p>
<p style="color: #999; font-size: 12px;"><a href="{$baseURL}">3秒后跳转到登录页</a></p>
<script>setTimeout("window.location.href = '/auth/login#login';", 3000);</script>
</body>
</html>
EOF;
                    echo $html;
                    exit();
                }
            }

            $html = <<<EOF
<!DOCTYPE html>
<html lang="zh-cmn-Hans">
<head>

<meta charset="utf-8">
<title>Mailbox check failed - Account Registration</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="HandheldFriendly" content="true">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />

</head>
<body>
<p>The check fails, check timeout or checksum wrong.</p>
<p style="color: #999; font-size: 12px;"><a href="{$baseURL}">3 seconds after the jump to the login page</a></p>
<script>setTimeout("window.location.href = '/auth/login#login';", 3000);</script>
</body>
</html>
EOF;
            echo $html;
            exit();
        }
    }

    /**
     * @JSON
     * @throws \Core\Error
     */
    public function forgePwd()
    {
        $result = array('error' => 1, 'message' => 'Forgot your password request failed. Please refresh the page and try again.');
        $siteName = SITE_NAME;

        if (isset($_POST['email']) && $_POST['email'] != '') {

            $user = User::getUserByEmail(htmlspecialchars(trim($_POST['email'])));
            if (!$user) {
                return $result;
            }

            if ($user->enable == 0) {
                $verify_code = json_decode($user->forgePwdCode, true)['verification'];
                if ($verify_code != null) {
                    $result['message'] = 'Your account has not been evaluated mail check, please try again after checking is completed!';
                    return $result;
                }
            }

            $user->lastFindPasswdTime = time();
            if ($user->lastFindPasswdCount != 0 && $user->lastFindPasswdCount > 2) {
                $result['message'] = 'Forgot password retry count has reached the upper limit
!';
                return $result;
            }

            $code = Utils::randomChar(10);
            $forgePwdCode['code'] = $code;
            $forgePwdCode['time'] = time();

            $user->forgePwdCode = json_encode($forgePwdCode);
            $content = Option::get('custom_mail_forgePassword_content');
            $params = [
                'code' => $code,
                'nickname' => $user->nickname,
                'email' => $user->email,
                'useTraffic' => Utils::flowAutoShow($user->flow_up + $user->flow_down),
                'transfer' => Utils::flowAutoShow($user->transfer),
                'expireTime' => date('Y-m-d H:i:s', $user->expireTime)
            ];
            $content = Utils::placeholderReplace($content, $params);

            $mailer = Mailer::getInstance();
            $mail = new \Model\Mail();
            $mail->to = $user->email;
            $mail->subject = "[" . SITE_NAME . "] Password Recovery";
            $mail->content = $content;
            $mailer->toQueue(true); // Added to the message queue
            $isOk = $mailer->send($mail);

            $user->save();

            $result['uid'] = $user->uid;
            if ($isOk) {
                $result['message'] = 'Verification code has been sent to the registered e-mail address, please note that check! <br/> Do not turn this page, you need a verification code to verify your account ownership before resetting your password! !';
                $result['error'] = 0;
            } else {
                $result['message'] = 'Failed to send a message, please contact your system administrator to check the mail settings!';
                $result['error'] = 1;
            }


            return $result;
        } else {
            if ($_POST['code'] != '' && $_POST['uid'] != '') {
                $uid = $_POST['uid'];
                $code = trim($_POST['code']);
                $user = User::GetUserByUserId(trim($uid));
                $forgePwdCode = json_decode($user->forgePwdCode, true);

                // forgePwdCode.length > 1 and codes and the same time is not more than 600 seconds (10 minutes)
                if (count($forgePwdCode) > 1 && $forgePwdCode['code'] == $code && (time() - intval($forgePwdCode['time'])) < 600) {
                    $newPassword = Utils::randomChar(10);
                    $user->setPassword($newPassword);

                    $user->lastFindPasswdCount = 0;
                    $user->lastFindPasswdTime = 0;
                    $user->save();

                    $content = Option::get('custom_mail_forgePassword_content_2');
                    $params = [
                        'code' => $code,
                        'newPassword' => $newPassword,
                        'nickname' => $user->nickname,
                        'email' => $user->email,
                        'useTraffic' => Utils::flowAutoShow($user->flow_up + $user->flow_down),
                        'transfer' => Utils::flowAutoShow($user->transfer),
                        'expireTime' => date('Y-m-d H:i:s', $user->expireTime)
                    ];
                    $content = Utils::placeholderReplace($content, $params);

                    $mailer = Mailer::getInstance();
                    $mail = new \Model\Mail();
                    $mail->to = $user->email;
                    $mail->subject = "[" . SITE_NAME . "] Your new Password";
                    $mail->content = $content;
                    $mailer->toQueue(true); // Added to the message queue
                    $isOk = $mailer->send($mail);
                    if ($isOk) {
                        $result['message'] = 'The new password has been sent to the account email address. Please check!<br/> The new password has been sent to the account email address. Please check';
                        $result['error'] = 0;
                    } else {
                        $result['message'] = 'Failed to send a message, please contact your system administrator to check the mail settings!';
                        $result['error'] = 1;
                    }


                } else {
                    $result['message'] = 'Verification code verification code has expired or improperly filled out. Please confirm again';
                    $result['error'] = -1;
                }
                return $result;
            } else {
                Template::putContext('user', User::getCurrent());
                Template::setView('home/forgePwd');
            }
        }

        return $result;
    }

}
