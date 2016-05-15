<?php
/**
 * shadowsocks-panel
 * Add: 2016/4/11 9:53
 * Author: Sendya <18x@loacg.com>
 */

namespace Controller\Admin;

use Core\Error;
use Core\Template;
use Helper\Option;
use Model\User;

/**
 * Class Setting
 * @Admin
 * @Authorization
 * @package Controller\Admin
 */
class Setting
{

    /**
     * System Settings page
     */
    public function index()
    {
        $data['user'] = User::getCurrent();
        $data['custom_plan_name'] = json_decode(Option::get('custom_plan_name'), true);
        $data['custom_transfer_level'] = json_decode(Option::get('custom_transfer_level'), true);
        $data['check_transfer_max'] = Option::get('check_transfer_max');
        $data['check_transfer_min'] = Option::get('check_transfer_min');
        $data['user_test_day'] = Option::get('user_test_day');

        Template::setContext($data);
        Template::setView("admin/setting");
    }

    /**
     * Detailed parameters page
     */
    public function system()
    {
        $data['user'] = User::getCurrent();
        $data['options'] = Option::getOptions();

        Template::setContext($data);
        Template::setView("admin/system");
    }

    /**
     * Read the detailed configuration option
     *
     * @JSON
     */
    public function query()
    {
        $result['error'] = 0;
        $result['message'] = 'success';

        $result['modal'] = Option::get(trim($_GET['option']));
        $result['isArray'] = false;
        return $result;
    }

    /**
     * Modify
     * @JSON
     */
    public function update()
    {

        $result['error'] = 0;
        $result['message'] = 'Save to finish';
        if ($_POST['option_v'] != null && $_POST['option_k'] != null) {
            Option::set(trim($_POST['option_k']), trim($_POST['option_v']));
            // A system initialization settings
            Option::init();
        }
        return $result;
    }


    /**
     * @JSON
     */
    public function saveOther()
    {
        $check_transfer_max = intval(trim($_POST['check_transfer_max']));
        $check_transfer_min = intval(trim($_POST['check_transfer_min']));
        $user_test_day = intval(trim($_POST['user_test_day']));

        Option::set('check_transfer_max', $check_transfer_max);
        Option::set('check_transfer_min', $check_transfer_min);
        Option::set('user_test_day', $user_test_day);
        Option::init();

        $result['error'] = 0;
        $result['message'] = 'Saved';
        return $result;
    }

    /**
     * @JSON
     */
    public function saveTransfer()
    {
        $custom_transfer_level['A'] = intval($_POST['transfer-A']);
        $custom_transfer_level['B'] = intval($_POST['transfer-B']);
        $custom_transfer_level['C'] = intval($_POST['transfer-C']);
        $custom_transfer_level['D'] = intval($_POST['transfer-D']);
        $custom_transfer_level['VIP'] = intval($_POST['transfer-VIP']);

        Option::set('custom_transfer_level', json_encode($custom_transfer_level));
        Option::init();

        $result['error'] = 0;
        $result['message'] = 'Saved';
        return $result;
    }

    /**
     * @JSON
     */
    public function savePlanCustom()
    {
        $custom_plan_name['A'] = $_POST['setting-A'];
        $custom_plan_name['B'] = $_POST['setting-B'];
        $custom_plan_name['C'] = $_POST['setting-C'];
        $custom_plan_name['D'] = $_POST['setting-D'];
        $custom_plan_name['VIP'] = $_POST['setting-VIP'];
        $custom_plan_name['Z'] = $_POST['setting-Z'];

        Option::set('custom_plan_name', json_encode($custom_plan_name));
        Option::init();

        $result['error'] = 0;
        $result['message'] = 'Saved';
        return $result;
    }

    public function getCustomMailContentList()
    {
        $opts = Option::getLike('custom_mail_');
        Template::putContext('custom_mail_list', $opts);
    }

    /**
     * @JSON
     * @return array
     * @throws Error
     */
    public function saveCustomMailContent()
    {
        $type = $_POST['custom_type']; // Obtain a modified type
        if (strpos($type, 'custom_mail_') !== false) {
            $content = $_POST['content']; // Obtain the modified contents
            if(!$content) {
                throw new Error('Parameter error', 405);
            }
            Option::set('$type', $content);
            return array('error' => 0, 'message' => '保存完毕');
        } else {
            throw new Error('Parameter error', 405);
        }
    }
}