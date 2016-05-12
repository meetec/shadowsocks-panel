<?php
/**
 * SS-Panel
 * A simple Shadowsocks management system
 * Author: Sendya <18x@loacg.com>
 */
namespace Controller;

use Core\Error;
use Core\Template;
use Helper\Option;
use Helper\Stats;
use Helper\Util;
use Helper\Utils;
use Model\Invite;
use Model\Message;
use Model\Node;
use Model\User;

/**
 * Class Member
 * @Authorization
 * @package Controller
 */
class Member
{

    /**
     * Home Page Dashboard
     */
    public function index()
    {
        $user = User::getUserByUserId(User::getCurrent()->uid);
        $data['user'] = $user;

        $data['online'] = Stats::countOnline();
        $data['userCount'] = Stats::countUser();
        $data['useUserCount'] = Stats::countUseUser(); // The number of users used the service
        $data['checkCount'] = Stats::countSignUser();
        $data['onlineNum'] = 0.00; // default online number.
        if ($data['online'] !== 0 && $data['userCount'] !== 0) {
            $data['onlineNum'] = round($data['online'] / $data['userCount'], 2) * 100;
        }

        $data['allTransfer'] = Utils::flowAutoShow($user->transfer);
        $data['useTransfer'] = $user->flow_up + $user->flow_down; // round(() / Utils::mb(), 2);
        $data['slaTransfer'] = Utils::flowAutoShow($user->transfer - $data['useTransfer']);
        $data['pctTransfer'] = 0.00;
        if (is_numeric($data['useTransfer']) && $data['useTransfer'] > 0 && is_numeric($user->transfer) && $user->transfer > 0) {
            $data['pctTransfer'] = round($data['useTransfer'] / $user->transfer, 2) * 100;
        }
        $data['useTransfer'] = Utils::flowAutoShow($data['useTransfer'], 1);
        $tmp = explode(" ", $data['useTransfer']);
        $data['useTransfer'] = $tmp[0];
        $data['useTransferUnit'] = count($tmp) > 1 ? $tmp[1] : 'KB';
        $data['systemTransfer'] = round(Stats::countTransfer() / Utils::gb(), 2); // All user-generated traffic

        $data['checkedTime'] = date('m-d H:i', $user->lastCheckinTime);
        $data['lastOnlineTime'] = date('Y-m-d H:i:s', $user->lastConnTime);
        $data['checked'] = strtotime(date('Y-m-d 00:00:00', time())) > strtotime(date('Y-m-d H:i:s',
                $user->lastCheckinTime));
        $data['userIp'] = Utils::getUserIP();

        // Message
        $data['globalMessage'] = Message::getGlobalMessage();

        Template::setContext($data);
        Template::setView('panel/member');

    }

    /**
     * Node page
     */
    public function node()
    {
        $data['user'] = User::getCurrent();
        $data['nodes'] = Node::getNodeArray(0);
        $data['nodeVip'] = Node::getNodeArray(1);

        Template::setContext($data);
        Template::setView("panel/node");
    }

    /**
     *    Invite list
     *    2015.11.11 start
     */
    public function invite()
    {
        $data['user'] = User::getUserByUserId(User::getCurrent()->uid);
        $data['inviteList'] = Invite::getInvitesByUid($data['user']->uid, "0");

        Template::setContext($data);
        Template::setView("panel/invite");
    }

    /**
     *    User info page,
     *    2015.11.12 start
     */
    public function info()
    {
        $user = User::getUserByUserId(User::getCurrent()->uid);
        $json = json_decode($user->forgePwdCode, true);
        $flag = true;
        if(!$json || $json['verification']==null) {
            $flag= false;
        }
        Template::putContext('is_verification', $flag);
        Template::putContext('user', $user);
        Template::setView("panel/info");
    }

    /**
     * Modify the web login password
     * @JSON
     * @throws Error
     */
    public function changePassword()
    {
        $user = User::getUserByUserId(User::getCurrent()->uid);

        if ($_POST['nowpwd'] != null && $_POST['pwd'] != null) {
            $result = array('error' => 1, 'message' => 'Password modification fails.');
            $nowpwd = $_POST['nowpwd'];
            $pwd = $_POST['pwd'];
            if (!$user->verifyPassword($nowpwd)) { // Incorrect password
                $result['message'] = "The old password is wrong!";
                return $result;
            }
            if ($pwd == $nowpwd) {
                $result['message'] = "The new password and the old password can not be the same!";
                return $result;
            }
            if (strlen($pwd) < 6) {
                $result['message'] = "The new password can not be less than 6!";
                return $result;
            }
            $user->setPassword(htmlspecialchars($pwd));
            $user->save();
            $_SESSION['currentUser'] = null;

            $result['error'] = 0;
            $result['message'] = "Modify password successfully, please sign in again";
            return $result;
        } else {
            Template::putContext('user', $user);
            Template::setView("panel/changePassword");
        }
    }

    /**
     * Modify SS connection password
     * @JSON
     * @throws Error
     */
    public function changeSSPwd()
    {
        $user = User::getUserByUserId(User::getCurrent()->uid);

        if ($_POST['sspwd'] != null) {
            $ssPwd = trim($_POST['sspwd']);
            if ($_POST['sspwd'] == '1') {
                $ssPwd = Utils::randomChar(8);
            }
            $user->sspwd = $ssPwd;
            $user->save();
            $_SESSION['currentUser'] = $user;
            $result = array('error' => 0, 'message' => 'Modify SS connection password success', 'sspwd' => $ssPwd);
            return $result;
        } else {
            Template::putContext('user', $user);
            Template::setView("panel/changeSSPassword");
        }
    }

    /**
     * Modify nickname
     * @JSON
     * @throws Error
     */
    public function changeNickname()
    {
        $user = User::getCurrent();
        if ($_POST['nickname'] != null) {

            $user = User::getUserByUserId($user->uid);
            $user->nickname = htmlspecialchars(trim($_POST['nickname']));
            $user->save();
            $_SESSION['currentUser'] = $user;
            return array('error' => 0, 'message' => 'Modify nickname successful, refresh the page or log into force.');
        } else {
            Template::putContext('user', $user);
            Template::setView("panel/changeNickname");
        }
    }

    /**
     * Edit custom encryption
     * @JSON
     * @throws Error
     */
    public function changeMethod()
    {
        $user = User::getCurrent();
        if ($_POST['method'] != null) {
            $method = null;
            if($_POST['method'] != '-1') {
                $method = htmlspecialchars(trim($_POST['method']));
            }
            $user = User::getUserByUserId($user->uid);
            $user->method = $method;
            $user->save();
            $_SESSION['currentUser'] = $user;
            return array('error' => 0, 'message' => 'Modify encryption method is successful, the entire node synchronization effect within about 5 minutes.');
        } else {
            $nodeList = Node::getSupportCustomMethodArray();
            Template::putContext('user', $user);
            Template::putContext('nodeList', $nodeList);
            Template::setView("panel/changeMethod");
        }
    }

    /**
     * Upgrade panel Plan
     * @JSON
     * @throws Error
     */
    public function changePlan()
    {
        Template::putContext('user', User::getUserByUserId(User::getCurrent()->uid));
        Template::setView("panel/changePlanLevel");
    }

    /**
     * Home Upgrade Package button
     * @JSON
     */
    public function updatePlan()
    {
        $user = User::getUserByUserId(User::getCurrent()->uid);

        $custom_transfer_level = json_decode(Option::get('custom_transfer_level'), true);

        $result = array('error' => 1, 'message' => 'Upgrading account type failure.');
        switch ($user->plan) {
            case 'A':
                if ($user->money >= 15) {
                    $user->money = $user->money - 15; // Package A upgrade to B deduct 15
                    $user->plan = 'B';
                    $user->transfer = Utils::GB * intval($custom_transfer_level['B']);
                    $user->save();
                    $_SESSION['currentUser'] = $user;
                    $result['error'] = 0;
                    $result['message'] = 'The upgrade is successful, your current level is A';
                } else {
                    $result['message'] = 'The upgrade fails, your balance';
                }
                break;
            case 'B':
                if ($user->money >= 25) {
                    $user->money = $user->money - 25;//Package B upgrade to C deduct 25
                    $user->plan = 'C';
                    $user->transfer = Utils::GB * intval($custom_transfer_level['C']);
                    $user->save();
                    $_SESSION['currentUser'] = $user;
                    $result['error'] = 0;
                    $result['message'] = 'The upgrade is successful, your current level is B';
                } else {
                    $result['message'] = 'The upgrade fails, your balance';
                }
                break;
            case 'C':
                if ($user->money >= 40) {
                    $user->money = $user->money - 40;//Package C upgrade to D deduct 40
                    $user->plan = 'D';
                    $user->transfer = Utils::GB * intval($custom_transfer_level['D']);
                    $user->save();
                    $_SESSION['currentUser'] = $user;
                    $result['error'] = 0;
                    $result['message'] = 'The upgrade is successful, your current level is C';
                } else {
                    $result['message'] = 'The upgrade fails, your balance';
                }
                break;
            case 'VIP':
                $result['error'] = 0;
                $result['message'] = 'The upgrade is successful';
                break;
            default:
                $result['message'] = 'The upgrade fails';
                break;
        }

        $result['level'] = Utils::planAutoShow($user->plan);
        return $result;

    }

    /**
     * @JSON
     * @return array
     * @throws Error
     */
    public function buyTransfer()
    {
        $user = User::getCurrent();
        if (!$user) {
            throw new Error('login timeout', 405);
        }
        $user = User::getUserByUserId($user->uid);
        if (($user->transfer - $user->getUseTransfer()) > Utils::GB * 10) {
            throw new Error('Traffic is still adequate, no need to purchase temporary traffic', 200);
        }
        if ($user->money <= 0) {
            throw new Error('Your account do not have insufficient coin to purchase', 200);
        }
        if($user->expireTime <= time()) {
            throw new Error('Tell you a secret, your account has expired, due account is unable to purchase traffic. You need to renew (●\'◡\'●)', 200);
        }
        $user->money--;
        $user->flow_down = $user->flow_down - Utils::GB;
        $user->enable = 1;
        $user->save();
        $_SESSION['currentUser'] = $user; // To update user information session in.
        return array('useTransfer' => Utils::flowAutoShow($user->getUseTransfer()), 'slaTransfer' => Utils::flowAutoShow($user->transfer - $user->getUseTransfer()), 'money' => $user->money, 'message' => 'Launch skill system, the traffic that you used previously subtracted 1GB. Now you can continue to use the');
    }

    public function actCard()
    {
        Template::putContext('user', User::getUserByUserId(User::getCurrent()->uid));
        Template::setView('panel/actCard');
    }

    /**
     * @JSON
     * @return array
     */
    public function expireDate()
    {

        $user = User::getUserByUserId(User::getCurrent()->uid);
        $expireTime = date('Y-m-d H:i:s', $user->expireTime);
        return array('error' => 0, 'message' => 'Your expiration time:' . $expireTime, 'expireTime' => $expireTime);
    }

    /**
     * Sign in
     *
     * @JSON
     */
    public function checkIn()
    {
        $user = User::getCurrent();
        $result = array('error' => 1, 'message' => 'Sign or failed to sign.');
        if ($user->lastCheckinTime <= strtotime(date('Y-m-d 00:00:00', time()))) {
            $user = User::getUserByUserId($user->uid);
            $user->lastCheckinTime = time();
            $checkinTransfer = rand(intval(Option::get('check_transfer_min')),
                    intval(Option::get('check_transfer_max'))) * Utils::MB;
            $user->transfer = $user->transfer + $checkinTransfer;
            $_SESSION['currentUser'] = $user;
            $user->save();
            $result['time'] = date("m-d H:i:s", $user->lastCheckinTime);
            $result['message'] = 'Signin successful, you obtain' . Utils::flowAutoShow($checkinTransfer) . ' flow';
            $result['error'] = 0;

        } else {
            $result['message'] = 'You already ' . date('Y-m-d H:i:s', $user->lastCheckinTime) . " were checked in.";
        }
        return $result;
    }

    /**
     * Delete your account (this site completely empty your registered account)
     *
     * @JSON
     * @return array
     */
    public function deleteMe()
    {
        $user = User::getCurrent();

        $flag = $_POST['delete'];
        $result = array('error' => 1, "message" => "Request Error");
        if ($flag != null && $flag == '1') {
            $user->delete();
            $result = array("error" => 0, "message" => "You have to eliminate all the memories from this site will be executed in three seconds after initialization world ...<br/>I wish you a pleasant stay.");
            $_SESSION['currentUser'] = null;
            setcookie("uid", '', time() - 3600, "/");
            setcookie("expire", '', time() - 3600, "/");
            setcookie("token", '', time() - 3600, "/");
        }

        return $result;
    }
}