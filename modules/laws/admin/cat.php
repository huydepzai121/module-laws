<?php

/**
 * NukeViet Content Management System
 * @version 4.x
 * @author VINADES.,JSC <contact@vinades.vn>
 * @copyright (C) 2009-2023 VINADES.,JSC. All rights reserved
 * @license GNU/GPL version 2 or any later version
 * @see https://github.com/nukeviet The NukeViet CMS GitHub project
 */

if (!defined('NV_IS_FILE_ADMIN')) {
    die('Stop!!!');
}

$page_title = $nv_Lang->getModule('cat_manager');

if ($nv_Request->isset_request('get_alias_title', 'post')) {
    $alias = $nv_Request->get_title('get_alias_title', 'post', '');
    $alias = change_alias($alias);
    nv_htmlOutput($alias);
}

$contents = "";
$catList = nv_catList();

if (empty($catList) and !$nv_Request->isset_request('add', 'get')) {
    nv_redirect_location(NV_BASE_ADMINURL . "index.php?" . NV_LANG_VARIABLE . "=" . NV_LANG_DATA . "&" . NV_NAME_VARIABLE . "=" . $module_name . "&" . NV_OP_VARIABLE . "=cat&add");
}

if ($nv_Request->isset_request('cWeight, id', 'post')) {
    $id = $nv_Request->get_int('id', 'post');
    $cWeight = $nv_Request->get_int('cWeight', 'post');
    if (!isset($catList[$id])) die("ERROR");

    if ($cWeight > $catList[$id]['pcount']) $cWeight = $catList[$id]['pcount'];

    $sql = "SELECT id FROM " . NV_PREFIXLANG . "_" . $module_data . "_cat WHERE parentid=" . intval($catList[$id]['parentid']) . " AND id!=" . $id . " ORDER BY weight ASC";
    $result = $db->query($sql);
    $weight = 0;
    while ($row = $result->fetch()) {
        $weight++;
        if ($weight == $cWeight) $weight++;
        $query = "UPDATE " . NV_PREFIXLANG . "_" . $module_data . "_cat SET weight=" . $weight . " WHERE id=" . $row['id'];
        $db->query($query);
    }
    $query = "UPDATE " . NV_PREFIXLANG . "_" . $module_data . "_cat SET weight=" . $cWeight . " WHERE id=" . $id;
    $db->query($query);
    $nv_Cache->delMod($module_name);
    nv_insert_logs(NV_LANG_DATA, $module_name, $nv_Lang->getModule('logChangeWeight'), "Id: " . $id, $admin_info['userid']);
    nv_htmlOutput('OK');
}

if ($nv_Request->isset_request('newday', 'post')) {
    $catid = $nv_Request->get_int('catid', 'post', 0);
    $new_vid = $nv_Request->get_int('new_vid', 'post', 0);

    $result = $db->query('UPDATE ' . NV_PREFIXLANG . '_' . $module_data . '_cat SET newday=' . $new_vid . ' WHERE id=' . $catid);
    if ($result) {
        $nv_Cache->delMod($module_name);
        nv_htmlOutput('OK');
    }
    nv_htmlOutput('NO');
}

if ($nv_Request->isset_request('del', 'post')) {
    $id = $nv_Request->get_int('del', 'post', 0);
    $listid = $nv_Request->get_title('listid', 'post', '');
    $listid = $listid . ',' . $id;
    $listid = array_filter(array_unique(array_map('intval', explode(',', $listid))));

    $check_sub = 0;
    $check_row = 0;

    foreach ($listid as $id) {
        if (!isset($catList[$id])) {
            continue;
        }

        // Kiểm tra có thể loại con thì không xóa
        if ($catList[$id]['count'] > 0) {
            $check_sub++;
            continue;
        }

        // Kiểm tra nếu có văn bản thì không xóa
        $sql = "SELECT COUNT(*) as count FROM " . NV_PREFIXLANG . "_" . $module_data . "_row WHERE cid=" . $id;
        $result = $db->query($sql);
        $row = $result->fetch();
        if ($row['count']) {
            $check_row++;
            continue;
        }

        nv_insert_logs(NV_LANG_DATA, $module_name, $nv_Lang->getModule('logDelCat'), "Id: " . $id, $admin_info['userid']);

        $query = "DELETE FROM " . NV_PREFIXLANG . "_" . $module_data . "_cat WHERE id = " . $id;
        $db->query($query);

        fix_catWeight($catList[$id]['parentid']);
    }

    $nv_Cache->delMod($module_name);

    if (sizeof($listid) == 1) {
        if ($check_sub > 0) {
            nv_htmlOutput($nv_Lang->getModule('errorCatYesSub'));
        }
        if ($check_row > 0) {
            nv_htmlOutput($nv_Lang->getModule('errorCatYesRow'));
        }
    } else {
        $error = [];
        if ($check_sub > 0) {
            $error[] = $nv_Lang->getModule('errorCatYesSub1');
        }
        if ($check_row > 0) {
            $error[] = $nv_Lang->getModule('errorCatYesRow1');
        }
        if (!empty($error)) {
            $error[] = $nv_Lang->getModule('errorCatDeleteList');
            nv_htmlOutput(implode("\n", $error));
        }
    }

    nv_htmlOutput('OK');
}

$xtpl = new XTemplate($op . ".tpl", NV_ROOTDIR . "/themes/" . $global_config['module_theme'] . "/modules/" . $module_file);
$xtpl->assign('LANG', \NukeViet\Core\Language::$lang_module);
$xtpl->assign('GLANG', \NukeViet\Core\Language::$lang_global);
$xtpl->assign('NV_BASE_SITEURL', NV_BASE_SITEURL);
$xtpl->assign('MODULE_URL', NV_BASE_ADMINURL . "index.php?" . NV_LANG_VARIABLE . "=" . NV_LANG_DATA . "&" . NV_NAME_VARIABLE . "=" . $module_name . "&" . NV_OP_VARIABLE);

if ($nv_Request->isset_request('add', 'get') or $nv_Request->isset_request('edit, id', 'get')) {
    $post = [];
    if ($nv_Request->isset_request('edit', 'get')) {
        $post['id'] = $nv_Request->get_int('id', 'get');
        if (empty($post['id']) or !isset($catList[$post['id']])) {
            nv_redirect_location(NV_BASE_ADMINURL . "index.php?" . NV_LANG_VARIABLE . "=" . NV_LANG_DATA . "&" . NV_NAME_VARIABLE . "=" . $module_name . "&" . NV_OP_VARIABLE . "=cat");
        }

        $xtpl->assign('PTITLE', $nv_Lang->getModule('editCat'));
        $xtpl->assign('ACTION_URL', NV_BASE_ADMINURL . "index.php?" . NV_LANG_VARIABLE . "=" . NV_LANG_DATA . "&" . NV_NAME_VARIABLE . "=" . $module_name . "&" . NV_OP_VARIABLE . "=cat&edit&id=" . $post['id']);
        $log_title = $nv_Lang->getModule('editCat');
    } else {
        $xtpl->assign('PTITLE', $nv_Lang->getModule('addCat'));
        $xtpl->assign('ACTION_URL', NV_BASE_ADMINURL . "index.php?" . NV_LANG_VARIABLE . "=" . NV_LANG_DATA . "&" . NV_NAME_VARIABLE . "=" . $module_name . "&" . NV_OP_VARIABLE . "=cat&add");
        $log_title = $nv_Lang->getModule('addCat');
    }

    if ($nv_Request->isset_request('save', 'post')) {
        $post['parentid'] = $nv_Request->get_int('parentid', 'post', 0);
        $post['title'] = $nv_Request->get_title('title', 'post', '', 1);
        $post['introduction'] = $nv_Request->get_title('introduction', 'post', '', 1);
        $post['introduction'] = nv_nl2br($post['introduction'], "<br />");
        $post['keywords'] = $nv_Request->get_title('keywords', 'post', '', 1);
        if (!empty($post['keywords'])) {
            $post['keywords'] = explode(",", $post['keywords']);
            $post['keywords'] = array_map("trim", $post['keywords']);
            $post['keywords'] = array_unique($post['keywords']);
            $post['keywords'] = implode(",", $post['keywords']);
        }

        $post['alias'] = $nv_Request->get_title('alias', 'post', '', 1);
        if (empty($post['alias'])) {
            $post['alias'] = change_alias($post['title']);

            $stmt = $db->prepare('SELECT COUNT(*) FROM ' . NV_PREFIXLANG . '_' . $module_data . '_cat WHERE ' . (isset($post['id']) ? (' id!=' . $post['id'] . ' AND ') : '') . ' alias = :alias');
            $stmt->bindParam(':alias', $post['alias'], PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->fetchColumn()) {
                $weight = $db->query('SELECT MAX(id) FROM ' . NV_PREFIXLANG . '_' . $module_data . '_cat')->fetchColumn();
                $weight = intval($weight) + 1;
                $post['alias'] = $post['alias'] . '-' . $weight;
            }
        }

        if (empty($post['title'])) {
            die($nv_Lang->getModule('errorIsEmpty') . ": " . $nv_Lang->getModule('title'));
        }

        $_catList = $catList;
        if (isset($post['id'])) unset($_catList[$post['id']]);

        if (!isset($catList[$post['parentid']])) $post['parentid'] = 0;

        $if_fixWeight = false;

        if (isset($post['id'])) {
            $weight = $catList[$post['id']]['weight'];
            if ($post['parentid'] != $catList[$post['id']]['parentid']) {
                $sql = "SELECT MAX(weight) as nweight FROM " . NV_PREFIXLANG . "_" . $module_data . "_cat WHERE parentid=" . $post['parentid'];
                if (($result = $db->query($sql)) !== false) {
                    $weight = $result->fetchColumn();
                    $weight++;
                } else {
                    $weight = 1;
                }
                $if_fixWeight = $catList[$post['id']]['parentid'];
            }

            $query = "UPDATE " . NV_PREFIXLANG . "_" . $module_data . "_cat SET
                    parentid=" . $post['parentid'] . ",
                    alias=" . $db->quote($post['alias']) . ",
                    title=" . $db->quote($post['title']) . ",
                    introduction=" . $db->quote($post['introduction']) . ",
                    keywords=" . $db->quote($post['keywords']) . ",
                    weight=" . $weight . " WHERE id=" . $post['id'];
            $db->query($query);
        } else {
            $sql = "SELECT MAX(weight) as nweight FROM " . NV_PREFIXLANG . "_" . $module_data . "_cat WHERE parentid=" . $post['parentid'];
            if (($result = $db->query($sql)) !== false) {
                $weight = $result->fetchColumn();
                $weight++;
            } else {
                $weight = 1;
            }

            $query = "INSERT INTO " . NV_PREFIXLANG . "_" . $module_data . "_cat (id, parentid, alias, title, introduction, keywords, addtime, weight)
                VALUES (NULL, " . $post['parentid'] . ", '', " . $db->quote($post['title']) . ",
                " . $db->quote($post['introduction']) . ", " . $db->quote($post['keywords']) . ",
                " . NV_CURRENTTIME . ", " . $weight . ");";
            $post['id'] = $db->insert_id($query);

            $query = "UPDATE " . NV_PREFIXLANG . "_" . $module_data . "_cat SET
                alias=" . $db->quote($post['alias']) . " WHERE id=" . $post['id'];
            $db->query($query);
        }

        if ($if_fixWeight !== false) fix_catWeight($if_fixWeight);
        $nv_Cache->delMod($module_name);
        nv_insert_logs(NV_LANG_DATA, $module_name, $log_title, "Id: " . $post['id'], $admin_info['userid']);
        nv_htmlOutput('OK');
    }

    if ($nv_Request->isset_request('edit', 'get')) {
        $sql = "SELECT * FROM " . NV_PREFIXLANG . "_" . $module_data . "_cat WHERE id=" . $post['id'];
        $result = $db->query($sql);
        $row = $result->fetch();
        $post['title'] = $row['title'];
        $post['alias'] = $row['alias'];
        $post['parentid'] = $row['parentid'];
        $post['introduction'] = nv_br2nl($row['introduction']);
        $post['keywords'] = $row['keywords'];
    } else {
        $post['title'] = "";
        $post['alias'] = "";
        $post['parentid'] = 0;
        $post['introduction'] = "";
        $post['keywords'] = "";
    }

    $ig = [];
    if ($nv_Request->isset_request('edit', 'get')) {
        array_unshift($ig, $post['id']);
        unset($catList[$post['id']]);
    }

    $is_optgroup = false;
    foreach ($catList as $id => $values) {
        if (!in_array($values['parentid'], $ig)) {
            $selected = $id == $post['parentid'] ? " selected=\"selected\"" : "";
            $style = $values['parentid'] == 0 ? " class=\"optmain\"" : "";
            $option = [
                'value' => $id,
                'name' => $values['name'],
                'selected' => $selected,
                'style' => $style
            ];

            $xtpl->assign('OPTION', $option);
            $xtpl->parse('dListOption');
        } else {
            array_unshift($ig, $id);
        }
    }

    $select = $xtpl->text('dListOption');
    $xtpl->assign('PARENTID', $select);
    $xtpl->assign('CAT', $post);

    if (empty($post['id'])) {
        $xtpl->parse('action.auto_get_alias');
    }

    $xtpl->parse('action');
    $contents = $xtpl->text('action');

    include NV_ROOTDIR . '/includes/header.php';
    echo nv_admin_theme($contents);
    include NV_ROOTDIR . '/includes/footer.php';
    exit();
}

if ($nv_Request->isset_request('list', 'get')) {
    $parentid = $nv_Request->get_int('parentid', 'get', 0);

    $xtpl->assign('PARENTID', $parentid);

    $a = 0;
    foreach ($catList as $id => $values) {
        if ($values['parentid'] == $parentid) {

            $loop = [
                'id' => $id,
                'title' => $values['title'],
                'count' => $values['count']
            ];
            $xtpl->assign('LOOP', $loop);

            for ($i = 1; $i <= $values['pcount']; $i++) {
                $opt = [
                    'value' => $i,
                    'selected' => $i == $values['weight'] ? " selected=\"selected\"" : ""
                ];
                $xtpl->assign('NEWWEIGHT', $opt);
                $xtpl->parse('list.loop.option');
            }

            for ($i = 1; $i <= 10; $i++) {
                $opt = [
                    'value' => $i,
                    'selected' => $i == $values['newday'] ? " selected=\"selected\"" : ""
                ];
                $xtpl->assign('NEWDAY', $opt);
                $xtpl->parse('list.loop.newday');
            }

            if ($loop['count'] != 0)
                $xtpl->parse('list.loop.count');
            else
                $xtpl->parse('list.loop.countEmpty');

            $xtpl->parse('list.loop');
            $a++;
        }
    }
    $xtpl->parse('list');
    $xtpl->out('list');
    exit();
}

$xtpl->parse('main');
$contents = $xtpl->text('main');

include NV_ROOTDIR . '/includes/header.php';
echo nv_admin_theme($contents);
include NV_ROOTDIR . '/includes/footer.php';
