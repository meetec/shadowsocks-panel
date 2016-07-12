<?php
/**
 * Project: shadowsocks-panel
 * Author: Sendya <18x@loacg.com>
 * Time: 2016/3/21 22:32
 */


namespace Controller;

use Core\Template;
use Helper\Option;
use Helper\Utils;
use Model\Card as Mcard;
use Model\User;

/**
 * Class Card
 * @Authorization
 * @package Controller
 */
class Card
{

    /**
     * Activate (use) the card number
     * @JSON
     */
    public function activation()
    {

        $user = User::getUserByUserId(User::getCurrent()->uid);

        $result = array('error' => 1, 'message' => 'The card has been used or does not exist.');
        if ($_POST['actCard'] != null) {
            $actCard = htmlspecialchars(trim($_POST['actCard']));
            $card = Mcard::queryCard($actCard);
            if (!$card || !$card->status) {
                return $result;
            }

            $custom_transfer_level = json_decode(Option::get('custom_transfer_level'), true);
            $custom_transfer_repeat = json_decode(Option::get('custom_transfer_repeat'), true);

            /* 0 - package card, 1 - traffic card, 2 - test card, 3 - card balance */
            if ($card->type == 0) {

                if ($user->plan == 'Z' && $user->transfer > ($user->flow_up + $user->flow_down)) {
                    $result['message'] = 'Your data plan has not been used up. Unable to convert' . Utils::planAutoShow($card->info) . ' package';
                    return $result;
                }
                //判断账户卡号类型是否一致 一致则无视系统叠加开关进行 叠加时间
                $user->payTime = time();
                if (($user->flow_up + $user->flow_down) < $user->transfer) {
                    $user->enable = 1;
                } else {
                    $user->enable = 0;
                }
                $cardDay = 31;
                if (is_numeric($card->expireTime)) {
                    $cardDay = intval($card->expireTime);
                }
<<<<<<< HEAD
                if ($user->expireTime < time()) {
                    $user->expireTime = time() + (3600 * 24 * $cardDay); // Expire date
                } else {
                    $user->expireTime = $user->expireTime + (3600 * 24 * $cardDay); // Expire date
                }
                $result['message'] = 'Your account has been upgraded to ' . Utils::planAutoShow($user->plan) . ' ,Total traffic ' . Utils::flowAutoShow($user->transfer) . ', used ' . Utils::flowAutoShow($user->flow_down + $user->flow_up) . ', Expire date:' . date('Y-m-d H:i:s',
=======

                $expireTime = 0;

                if ($user->plan == $card->info) { // 卡片与账户类型相等
                    if ($user->expireTime > time()) {
                        $expireTime = $user->expireTime + (3600 * 24 * $cardDay);// 到期时间 = 当前账户到期时间+卡片时间
                    } else {
                        $expireTime = time() + (3600 * 24 * $cardDay); // 到期时间 = 当前系统续费时间+卡片时间
                    }
                } else {

                    if ($user->expireTime < time() || !$custom_transfer_repeat) {
                        $expireTime = time() + (3600 * 24 * $cardDay); // 到期时间 = 不叠加原时间 （当前系统续费时间+卡片时间）
                    } else {
                        $expireTime = $user->expireTime + (3600 * 24 * $cardDay); // 到期时间 = 当前账户到期时间+卡片时间
                    }
                }
                $user->expireTime = $expireTime;
                $user->plan = $card->info;
                $user->transfer = Utils::GB * intval($custom_transfer_level[$user->plan]);

                $result['message'] = '您的账户已升级到 ' . Utils::planAutoShow($user->plan) . ' ,共有流量 ' . Utils::flowAutoShow($user->transfer) . ', 已用 ' . Utils::flowAutoShow($user->flow_down + $user->flow_up) . ', 到期时间：' . date('Y-m-d H:i:s',
>>>>>>> sendya/master
                        $user->expireTime);
            } elseif ($card->type == 1) {
                if ($user->plan == 'Z') {
                    $user->transfer += intval($card->info) * Utils::GB; // If before the data plan, is incremented
                } else {
                    $user->transfer = intval($card->info) * Utils::GB; // If before the ordinary course, the total flow will be emptied and set new traffic
                    $user->flow_up = 0;
                    $user->flow_down = 0;
                }
                if (($user->flow_up + $user->flow_down) < $user->transfer) {
                    $user->enable = 1;
                } else {
                    $user->enable = 0;
                }
                $user->plan = 'Z'; // Forcibly set to Z
                $user->expireTime = strtotime("+1 year"); // Account the time available additional year
                $result['message'] = 'Your account has been activated fixed data plan, total flow' . Utils::flowAutoShow($user->transfer) . ' ,the expiration time traffic ' . date('Y-m-d H:i:s',
                        $user->expireTime) . ', Thank you for your use (Note: Data usage before completion can not be converted by the package card package monthly users)';

            } elseif ($card->type == 2) {
                $user_test_day = Option::get('user_test_day') ?: 7;

                if ($user->plan != 'A') {
                    return array('error' => 1, 'message' => 'Hey, you do not test account hey? Continued life can not help you.');
                }
                $user->plan = 'A';
                $user->payTime = time();
                if ($user->expireTime < time()) {
                    $user->expireTime = time() + (3600 * 24 * intval($user_test_day)); // Expire date
                } else {
                    $user->expireTime = $user->expireTime + (3600 * 24 * intval($user_test_day)); // Expire date
                }
                $user->transfer = Utils::GB * intval($custom_transfer_level[$user->plan]);
                $user->flow_down = 0;
                $user->flow_up = 0;
                $user->enable = 1;
                $result['message'] = 'Your account has been activated test packages, total flow' . Utils::flowAutoShow($user->transfer) . ' ,Expire date ' . date('Y-m-d H:i:s',
                        $user->expireTime) . ', Thank you for using';
            } elseif ($card->type == 3) {
                // Balance card
                $user->money += intval($card->info);
                $user->save();
                $result['message'] = 'Balance successful recharge, your current balance ' . $user->money . ' yuan';
            }
            $card->destroy(); // This card is prohibited
            $user->save();
            $_SESSION['currentUser'] = $user; // Will update the user information in the session.

        }

        return $result;
    }

}