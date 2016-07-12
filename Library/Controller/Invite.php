<?php
/**
 * SS-Panel
 * A simple Shadowsocks management system
 * Author: Sendya <18x@loacg.com>
 */

namespace Controller;

use \Core\Template;

use Helper\Utils;
use Model\User;
use \Model\Invite as InviteModel;


class Invite
{

    public function index()
    {
        $inviteList = InviteModel::getInviteArray(-1);
        Template::setView('home/invite');
        Template::putContext('inviteList', $inviteList);
    }

    /**
     * Invitation code generation，Necessary permission checks
     *
     * @JSON
     * @Authorization
     */
    public function create()
    {
        $user = User::getUserByUserId(User::getCurrent()->uid);

<<<<<<< HEAD
        $result = array('error' => 1, 'message' => 'Create invitation code fails，You do not have an invitation code to create that number of times again. You can always purchase invitation code with exchange for traffic. (10GB / per invitations)');
=======
        $result = array('error' => 1, 'message' => '创建邀请码失败，您没有再次创建邀请码的次数了。当然，你可以用流量购买次数。(10GB/个)');

>>>>>>> sendya/master
        if ($user->invite_num > 0) {
            $invite = InviteModel::addInvite($user->uid, 'A', false);
            $result = array(
                'error' => 0,
                'message' => 'Create invitation code is successful, to view please refresh',
                'invite_num' => $user->invite_num - 1,
                'invite' => $invite
            );
            $user->invite_num = $user->invite_num - 1;
            $user->save();
        }

        return $result;
    }

    /**
     * Buy invitation code, necessary permission checks
     *
     * @JSON
     * @Authorization
     * @return array
     */
    public function buy()
    {
        $user = User::getUserByUserId(User::getCurrent()->uid);
        $result = array('error' => 1, 'message' => 'Purchase fails, you need at least 20GB traffic to buy invitation code.');
        $transfer = Utils::GB * 10;
        if ($user->transfer > ($transfer * 2)) {
            $user->transfer = $user->transfer - $transfer;
            $user->invite_num = $user->invite_num + 1;
            $user->save();
            $result = array('error' => 0, 'message' => 'Successful purchase, with 10GB of traffic', 'invite_num' => $user->invite_num);
        }
        return $result;
    }

}
