<?php
/**
 * Project: shadowsocks-panel
 * Author: Sendya <18x@loacg.com>
 * Time: 2016/4/13 23:47
 */


namespace Controller\Admin;

use Core\Template;
use Helper\Utils;
use Model\User;
use Model\Card as MCard;

/**
 * Class Cron
 * @Admin
 * @Authorization
 * @package Controller\Admin
 */
class Card
{

    const API_KEY = 'adsmgu-+58iq[wpeoi^#5-+&^#@%2563DS.-+6GDQW784';

    public function index()
    {

        Template::putContext('cardList', MCard::queryAll());
        Template::putContext('user', User::getCurrent());
        Template::setView("admin/card");
    }

    /**
     * @JSON
     * @return array
     */
    public function query()
    {
        return array('error' => 0, 'message' => 'success', 'card' => MCard::queryCardById(trim($_POST['id'])));
    }

    /**
     * Modifications and new card
     *
     * @JSON
     */
    public function update()
    {
        $result = array('error' => 1, 'message' => 'Request Error');
        $user = User::getCurrent();
        if ($_POST['card_no'] != null && $_POST['card'] != null) { // modify
            $cardId = intval(trim($_POST['card']));
            $card = MCard::queryCardById($cardId);
            if (!$card) {
                return $result;
            }

            $card->type = intval(trim($_POST['card_type']));
            $card->info = htmlspecialchars(trim($_POST['card_info']));
            $card->status = intval(trim($_POST['card_status']));
            $card->expireTime = intval(trim($_POST['card_exp']));
            $card->save();
            $card->add_time = date("Y-m-d H:i:s", $card->add_time);
            if($card->type == 0) {
                $card->type = "Package card";
            } elseif ($card->type == 1) {
                $card->type = "Traffic Cards";
            } elseif ($card->type == 2) {
                $card->type = "Trial postponed Cards";
            } elseif ($card->type == 3) {
                $card->type = "Balance card";
            }

            $card->status = $card->status == 1 ? "Unused" : "used";
            $result['error'] = 0;
            $result['message'] = "Modify card success.";
            $result['card'] = $card;
            return $result;
        } else { // New

            $number = 1;
            if ($_POST['card_num'] != null) {
                $number = intval(trim($_POST['card_num']));
            }
            $cardList = array();
            for ($i = 0; $i < $number; ++$i) {
                $cardStr = substr(hash("sha256", $user->uid . Utils::randomChar(10)) . time(), 1, 26);
                $card = new MCard();
                $card->add_time = time();
                $card->card = $cardStr;
                $card->type = intval(trim($_POST['card_type']));
                $card->info = htmlspecialchars(trim($_POST['card_info']));
                $card->expireTime = intval(trim($_POST['card_exp']));
                $card->status = 1;
                $card->save();
                $card->add_time = date("Y-m-d H:i:s", $card->add_time);
                if($card->type == 0) {
                    $card->type = "Package card";
                } elseif ($card->type == 1) {
                    $card->type = "Traffic Cards";
                } elseif ($card->type == 2) {
                    $card->type = "Trial postponed Cards";
                } elseif ($card->type == 3) {
                    $card->type = "Balance card";
                }

                $card->status = $card->status == 1 ? "Unused" : "Used";
                $cardList[] = $card;
            }
            $result['error'] = 0;
            $result['message'] = "The new card is successful, a total of {$number} A.";
            $result['card'] = $cardList;
            return $result;
        }
    }

    /**
     * Delete card
     * @JSON
     */
    public function delete()
    {
        MCard::queryCardById(trim($_POST['id']))->delete();
        return array('error' => 0, 'message' => 'successfully deleted');
    }

}