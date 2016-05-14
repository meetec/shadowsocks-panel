<?php
/**
 * Created by IntelliJ IDEA.
 * User: Sendya
 * Date: 2016/3/18
 * Time: 11:31
 */

namespace Controller\Admin;

use \Core\Template;

use \Model\Message as MessageModel;
use Model\User;

/**
 * Controller: Message Management
 * @Admin
 * @Authorization
 */
class Message
{

    public function index()
    {
        $data['user'] = User::getCurrent();
        $data['lists'] = MessageModel::getPushMessage(-1);
        Template::setContext($data);
        Template::setView('admin/message');
    }

    /**
     * New or Modify
     *
     * @JSON
     * @return array
     */
    public function update()
    {

        $result = array('error' => 1, 'message' => 'Request failed');

        if ($_POST['message_id'] != null) { // Modify
            $msg = MessageModel::getMessageById(trim($_POST['message_id']));
            if ($msg) { // Modify
                $msg->content = $_POST['message_content'] == null ? "" : $_POST['message_content'];
                $msg->pushTime = $_POST['message_pushTime'] == null ? 0 : strtotime($_POST['message_pushTime']);
                $msg->pushUsers = $_POST['message_pushUsers'] == null ? -1 : $_POST['message_pushUsers'];
                $msg->type = $_POST['message_type'];
                $msg->pushEndTime = $_POST['message_pushEndTime'] == null ? 0 : strtotime($_POST['message_pushEndTime']);
                $msg->enable = $_POST['message_enable'] == null ? 0 : $_POST['message_enable'];
                $msg->save();
                $result = array('error' => 0, 'message' => 'Update completed');
            }
        } else {
            $msg = new MessageModel();
            $msg->content = $_POST['message_content'] == null ? "" : $_POST['message_content'];
            $msg->pushTime = $_POST['message_pushTime'] == null ? 0 : strtotime($_POST['message_pushTime']);
            $msg->pushUsers = $_POST['message_pushUsers'] == null ? 0 : $_POST['message_pushUsers'];
            $msg->type = $_POST['message_type'];
            $msg->pushEndTime = $_POST['message_pushEndTime'] == null ? 0 : strtotime($_POST['message_pushEndTime']);
            $msg->enable = $_POST['message_enable'] == null ? 0 : $_POST['message_enable'];
            $msg->save();
            $result = array('error' => 0, 'message' => 'Add news Success');
        }
        $msg->content = nl2br(mb_substr(htmlspecialchars($msg->content), 0, 500, 'utf-8'));
        $msg->pushEndTime = date('Y-m-d H:i:s', $msg->pushEndTime);
        $type = "";
        switch ($msg->type) {
            case '-1':
                $type = "Duplicate messages";
                break;
            case '-2':
                $type = "System notification";
                break;
            case '-3':
                $type = "Packages at the instructions";
                break;
            case '-4':
                $type = "Tooltips";
                break;
            case '-5':
                $type = "Login page announcement";
                break;
            case '0':
            default:
                $type = "Normal message";
                break;
        }
        $msg->type = $type;
        $pushTo = "";
        switch ($msg->pushUsers) {
            case '-2':
                $pushTo = "Fixed message system";
                break;
            case '-1':
                $pushTo = "system information";
                break;
            default:
                $pushTo = "user:" . $msg->pushUsers;
                break;
        }
        $msg->pushUsers = $pushTo;
        $result['modal'] = $msg;

        return $result;
    }

    /**
     * Delete
     * @JSON
     */
    public function delete()
    {
        $result = array('error' => 1, 'message' => 'Request failed');

        if ($_POST['message_id'] != null) {
            MessageModel::deleteMessageById(intval(trim($_POST['message_id'])));
            $result = array('error' => 0, 'message' => 'Successfully Deleted');
        }
        return $result;
    }

    /**
     * Inquire
     * @JSON
     */
    public function query()
    {
        $result = array('error' => 1, 'message' => 'Request failed');
        $result['message_id'] = $_GET['message_id'];
        if ($_GET['message_id'] != null) {

            $rs = MessageModel::getMessageById(trim($_GET['message_id']));
            $rs->pushTime = date('Y-m-d H:i:s', $rs->pushTime);
            $rs->pushEndTime = date('Y-m-d H:i:s', $rs->pushEndTime);
            if ($rs) {
                $result = array('error' => 0, 'message' => 'success', 'modal' => $rs);
            }
        }
        return $result;
    }

}