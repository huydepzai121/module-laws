<?php

/**
 * NukeViet Content Management System
 * @version 4.x
 * @author VINADES.,JSC <contact@vinades.vn>
 * @copyright (C) 2009-2023 VINADES.,JSC. All rights reserved
 * @license GNU/GPL version 2 or any later version
 * @see https://github.com/nukeviet The NukeViet CMS GitHub project
 */

if (!defined('NV_IS_FILE_ADMIN')) die('Stop!!!');

$page_title = $nv_Lang->getModule('scontent');

$id = $nv_Request->get_int('id', 'get', 0);
$error = "";

if ($id) {
    $sql = "SELECT id, title, offices, positions FROM " . NV_PREFIXLANG . "_" . $module_data . "_signer WHERE id=" . $id;
    $result = $db->query($sql);
    $check = $result->rowCount();

    if ($check != 1) {
        nv_info_die($nv_Lang->getGlobal('error_404_title'), $nv_Lang->getGlobal('error_404_title'), $nv_Lang->getGlobal('error_404_content'));
    }

    list ($id, $title, $offices, $positions) = $result->fetch(3);

    $arraya_old = $array = array(
        "id" => $id,
        "title" => $title,
        "offices" => $offices,
        "positions" => $positions
    );

    $form_action = NV_BASE_ADMINURL . "index.php?" . NV_LANG_VARIABLE . "=" . NV_LANG_DATA . "&amp;" . NV_NAME_VARIABLE . "=" . $module_name . "&amp;" . NV_OP_VARIABLE . "=" . $op . "&amp;id=" . $id;
    $table_caption = $nv_Lang->getModule('scontent_edit');
} else {
    $form_action = NV_BASE_ADMINURL . "index.php?" . NV_LANG_VARIABLE . "=" . NV_LANG_DATA . "&amp;" . NV_NAME_VARIABLE . "=" . $module_name . "&amp;" . NV_OP_VARIABLE . "=" . $op;
    $table_caption = $nv_Lang->getModule('scontent_add');

    $array = array(
        "id" => 1,
        "title" => "",
        "offices" => "",
        "positions" => ""
    );
}

if ($nv_Request->isset_request('bntsubmit', 'post')) {
    $array['title'] = nv_substr($nv_Request->get_title('title', 'post', '', 1), 0, 255);
    $array['offices'] = nv_substr($nv_Request->get_title('offices', 'post', '', 1), 0, 255);
    $array['positions'] = nv_substr($nv_Request->get_title('positions', 'post', '', 1), 0, 255);

    // Check error
    if (empty($array['title'])) {
        $error = $nv_Lang->getModule('scontent_error_title');
    } else {
        if (empty($id)) {
            // Check exist
            $sql = "SELECT id FROM " . NV_PREFIXLANG . "_" . $module_data . "_signer WHERE title=" . $db->quote($array['title']) . " AND offices=" . $db->quote($array['offices']) . " AND positions=" . $db->quote($array['positions']);
            $result = $db->query($sql);
            list ($check_exist) = $result->fetch(3);

            if ($check_exist) {
                $error = $nv_Lang->getModule('scontent_error_exist');
            } else {
                // Insert into database
                $sql = "INSERT INTO " . NV_PREFIXLANG . "_" . $module_data . "_signer VALUES (
                    NULL,
                    " . $db->quote($array['title']) . ",
                    " . $db->quote($array['offices']) . ",
                    " . $db->quote($array['positions']) . ",
                    " . NV_CURRENTTIME . "
                )";

                if ($db->insert_id($sql)) {
                    $nv_Cache->delMod($module_name);
                    nv_insert_logs(NV_LANG_DATA, $module_name, $nv_Lang->getModule('scontent_add'), $array['title'], $admin_info['userid']);
                    nv_redirect_location(NV_BASE_ADMINURL . "index.php?" . NV_LANG_VARIABLE . "=" . NV_LANG_DATA . "&" . NV_NAME_VARIABLE . "=" . $module_name . "&" . NV_OP_VARIABLE . "=signer");
                } else {
                    $error = $nv_Lang->getModule('error_save');
                }
            }
        } else {
            // Check exist
            $sql = "SELECT id FROM " . NV_PREFIXLANG . "_" . $module_data . "_signer WHERE title=" . $db->quote($array['title']) . " AND id!=" . $id;
            $result = $db->query($sql);
            list ($check_exist) = $result->fetch(3);

            if ($check_exist) {
                $error = $nv_Lang->getModule('actor_error_exist');
            } else {
                $sql = "UPDATE " . NV_PREFIXLANG . "_" . $module_data . "_signer SET
                    title=" . $db->quote($array['title']) . ",
                    offices=" . $db->quote($array['offices']) . ",
                    positions=" . $db->quote($array['positions']) . "
                    WHERE id =" . $id;

                if ($db->query($sql)) {
                    $nv_Cache->delMod($module_name);
                    nv_insert_logs(NV_LANG_DATA, $module_name, $nv_Lang->getModule('scontent_edit'), $array_old['title'] . "&nbsp;=&gt;&nbsp;" . $array['title'], $admin_info['userid']);
                    nv_redirect_location(NV_BASE_ADMINURL . "index.php?" . NV_LANG_VARIABLE . "=" . NV_LANG_DATA . "&" . NV_NAME_VARIABLE . "=" . $module_name . "&" . NV_OP_VARIABLE . "=signer");
                } else {
                    $error = $nv_Lang->getModule('error_update');
                }
            }
        }
    }
}

$xtpl = new XTemplate("signer_content.tpl", NV_ROOTDIR . "/themes/" . $global_config['module_theme'] . "/modules/" . $module_file);
$xtpl->assign('LANG', \NukeViet\Core\Language::$lang_module);
$xtpl->assign('GLANG', \NukeViet\Core\Language::$lang_global);
$xtpl->assign('DATA', $array);
$xtpl->assign('TABLE_CAPTION', $table_caption);
$xtpl->assign('FORM_ACTION', $form_action);
$xtpl->assign('NV_BASE_ADMINURL', NV_BASE_ADMINURL);
$xtpl->assign('NV_NAME_VARIABLE', NV_NAME_VARIABLE);

// Prase error
if (!empty($error)) {
    $xtpl->assign('ERROR', $error);
    $xtpl->parse('main.error');
}

if ($nv_Request->isset_request('edit', 'get')) {
    $sql = "SELECT * FROM " . NV_PREFIXLANG . "_" . $module_data . "_signer WHERE id=" . $post['id'];
    $result = $db->query($sql);
    $row = $result->fetch();
    $post['title'] = $row['title'];
    $post['offices'] = $row['offices'];
    $post['positions'] = $row['positions'];
    $post['addtime'] = $row['addtime'];
} else {
    $post['title'] = "";
    $post['offices'] = "";
    $post['positions'] = "";
    $post['addtime'] = "";
}

$xtpl->parse('main');
$contents = $xtpl->text('main');

include NV_ROOTDIR . '/includes/header.php';
echo nv_admin_theme($contents);
include NV_ROOTDIR . '/includes/footer.php';
