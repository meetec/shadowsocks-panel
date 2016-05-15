<?php
/**
 * SS-Panel
 * A simple Shadowsocks management system
 * Author: Sendya <18x@loacg.com>
 */
namespace Controller;

use Core\Error;
use Core\Template;
use Helper\Message;
use Helper\Utils;
use Model\User;
use Model\Node as NodeModel;

/**
 * Class Node
 * @Authorization
 * @package Controller
 */
class Node
{
    public function Index()
    {
        throw new Error("Ignorance of human", 555);
    }

    /**
     * @JSON
     * @return array
     */
    public function getNodeInfo()
    {
        $id = trim($_REQUEST['id']);
        $result = array('error' => -1, 'message' => 'Request failed');
        $user = User::getUserByUserId(User::getCurrent()->uid);
        $node = NodeModel::getNodeById($id);
        $method = $node->method;
        if($node->custom_method == 1 && $user->method != '' && $user->method != null) {
            $method = $user->method;
        }
        $info = self::nodeDetail($node->server, $user->port, $user->sspwd, $method, $node->name);
        if (self::verifyPlan($user->plan, $node->type)) {
            $result = array('error' => 0, 'message' => '获取成功', 'info' => $info, 'node' => $node);
        } else {
<<<<<<< HEAD
            Message::show('You are not VIP, Unable to use the advanced node!', 'member/node');
=======
            $result = array('error' => -1, 'message' => '你不是 VIP, 无法使用高级节点！');
>>>>>>> sendya/master
        }
        return $result;
    }

<<<<<<< HEAD
    public function json()
    {
        $id = trim($_REQUEST['id']);
        $user = User::getUserByUserId(User::getCurrent()->uid);
        $node = NodeModel::getNodeById($id);
        $method = $node->method;
        if($node->custom_method == 1 && $user->method != '' && $user->method != null) {
            $method = $user->method;
        }
        $info = self::nodeJson($node->server, $user->port, $user->sspwd, $method, $node->name);
        if (self::verifyPlan($user->plan, $node->type)) {
            Template::putContext('info', $info);
            Template::putContext('node', $node);
            Template::setView('node/Json');
        } else {
            Message::show('You are not VIP, Unable to use the advanced node!', 'member/node');
        }
    }

    /**
     * Export node list
     */
    public function jsonList()
    {
        $user = User::getUserByUserId(User::getCurrent()->uid);
        $nodeList = NodeModel::getNodeArray();
        $info = "";

        foreach ($nodeList as $node) {
            $method = $node->method;
            if($node->custom_method == 1 && $user->method != '' && $user->method != null) {
                $method = $user->method;
            }
            if (self::verifyPlan($user->plan, $node->type)) {
                $info .= self::nodeJson($node->server, $user->port, $user->sspwd, $method, $node->name) . ",";
            }
        }
        Template::putContext('info', $info);
        Template::setView('node/JsonAll');
    }


    private static function nodeJson($server, $server_port, $password, $method, $name)
    {
        return '{"server":"' . $server . '","server_port":' . $server_port . ',"local_port":1080,' . '"password":"' . $password . '","timeout":600,' . '"method":"' . $method . '", "remarks": "' . $name . '"}';
    }

    private static function nodeQr($server, $server_port, $password, $method)
=======
    private static function nodeDetail($server, $server_port, $password, $method, $name)
>>>>>>> sendya/master
    {
        $ssurl = $method . ":" . $password . "@" . $server . ":" . $server_port;
        $ssurl = "ss://" . base64_encode($ssurl);
        $ssjsonAry = array("server" => $server, "server_port" => $server_port, "password" => $password, "timeout" => 600, "method" => $method, "remarks" => $name);
        $ssjson = json_encode($ssjsonAry, JSON_PRETTY_PRINT);
        return array("ssurl" => $ssurl, "ssjson" => $ssjson);
    }

    private static function verifyPlan($plan, $nodeType)
    {
        if ($nodeType == 1) {
            if ($plan == 'VIP' || $plan == 'SVIP') {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

}