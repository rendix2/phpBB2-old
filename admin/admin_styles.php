<?php
/***************************************************************************
 *                              admin_styles.php
 *                            -------------------
 *   begin                : Thursday, Jul 12, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: admin_styles.php 8377 2008-02-10 12:52:05Z acydburn $
 *
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

define('IN_PHPBB', 1);

if (!empty($setmodules) ) {
	$file = basename(__FILE__);
	$module['Styles']['Add_new'] = "$file?mode=addnew";
	$module['Styles']['Create_new'] = "$file?mode=create";
	$module['Styles']['Manage'] = $file;
	$module['Styles']['Export'] = "$file?mode=export";
	return;
}

//
// Load default header
//
//
// Check if the user has cancled a confirmation message.
//
$phpbb_root_path = "./../";

$confirm = isset($_POST['confirm']);
$cancel = isset($_POST['cancel']);

$no_page_header = !empty($_POST['send_file']) || !empty($_POST['send_file']) || $cancel;

require './pagestart.php';

if ($cancel) {
    redirect('admin/' . append_sid("admin_styles.php", true));
}

if (isset($_GET['mode']) || isset($_POST['mode'])) {
    $mode = isset($_GET['mode']) ? $_GET['mode'] : $_POST['mode'];
    $mode = htmlspecialchars($mode);
} else {
    $mode = "";
}

switch( $mode )
{
	case "addnew":
		$install_to = isset($_GET['install_to']) ? urldecode($_GET['install_to']) : $_POST['install_to'];
		$style_name = isset($_GET['style']) ? urldecode($_GET['style']) : $_POST['style'];
	
		if (isset($install_to) ) {
			include $phpbb_root_path. "templates/" . basename($install_to) . "/theme_info.cfg";

			$template_names = $$install_to;

			$insert_data = [];
			
			foreach ($template_names as $template_name) {
				if ($template_name['style_name'] == $style_name ) {
					foreach ($template_name as $key => $value) {
                        $insert_data[$key] = $value;
					}
				}
			}

			dibi::insert(THEMES_TABLE, $insert_data)->execute();
			
			$message = $lang['Theme_installed'] . "<br /><br />" . sprintf($lang['Click_return_styleadmin'], "<a href=\"" . append_sid("admin_styles.php") . "\">", "</a>") . "<br /><br />" . sprintf($lang['Click_return_admin_index'], "<a href=\"" . append_sid("index.php?pane=right") . "\">", "</a>");

			message_die(GENERAL_MESSAGE, $message);
		} else {
			$installable_themes = [];
			
			if ($dir = @opendir($phpbb_root_path. "templates/") ) {
				while ($sub_dir = @readdir($dir) ) {
					if (!is_file(phpbb_realpath($phpbb_root_path . 'templates/' .$sub_dir)) && !is_link(phpbb_realpath($phpbb_root_path . 'templates/' .$sub_dir)) && $sub_dir != "." && $sub_dir != ".." && $sub_dir != "CVS" ) {
						if (@file_exists(@phpbb_realpath($phpbb_root_path. "templates/" . $sub_dir . "/theme_info.cfg")) ) {
							include $phpbb_root_path. "templates/" . $sub_dir . "/theme_info.cfg";
							
							for ($i = 0; $i < count($$sub_dir); $i++) {
								$working_data = $$sub_dir;
								
								$style_name = $working_data[$i]['style_name'];

								$theme_check = dibi::select('themes_id')
                                    ->from(THEMES_TABLE)
                                    ->where('style_name = %s', $style_name)
                                    ->fetch();

								if (!$theme_check) {
									$installable_themes[] = $working_data[$i];
								}
							}
						}
					}
				}

                $template->set_filenames(["body" => "admin/styles_addnew_body.tpl"]);

                $template->assign_vars(
                    [
                        "L_STYLES_TITLE"    => $lang['Styles_admin'],
                        "L_STYLES_ADD_TEXT" => $lang['Styles_addnew_explain'],
                        "L_STYLE"           => $lang['Style'],
                        "L_TEMPLATE"        => $lang['Template'],
                        "L_INSTALL"         => $lang['Install'],
                        "L_ACTION"          => $lang['Action']
                    ]
                );

                foreach ($installable_themes as $installable_theme) {
					$row_color = ( !($i % 2) ) ? $theme['td_color1'] : $theme['td_color2'];
					$row_class = ( !($i % 2) ) ? $theme['td_class1'] : $theme['td_class2'];

                    $template->assign_block_vars("styles",
                        [
                            "ROW_CLASS"     => $row_class,
                            "ROW_COLOR"     => "#" . $row_color,
                            "STYLE_NAME"    => $installable_theme['style_name'],
                            "TEMPLATE_NAME" => $installable_theme['template_name'],

                            "U_STYLES_INSTALL" => append_sid("admin_styles.php?mode=addnew&amp;style=" . urlencode($installable_theme['style_name']) . "&amp;install_to=" . urlencode($installable_theme['template_name']))
                        ]
                    );

                }
				$template->pparse("body");
					
			}
			closedir($dir);
		}
		break;
	
	case "create":
	case "edit":
		$submit = isset($_POST['submit']) ? TRUE : 0;
		
		if ($submit )
		{
			//	
			// DAMN! Thats alot of data to validate...
			//
			$updated['style_name'] = $_POST['style_name'];
			$updated['template_name'] = $_POST['template_name'];
			$updated['head_stylesheet'] = $_POST['head_stylesheet'];
			$updated['body_background'] = $_POST['body_background'];
			$updated['body_bgcolor'] = $_POST['body_bgcolor'];
			$updated['body_text'] = $_POST['body_text'];
			$updated['body_link'] = $_POST['body_link'];
			$updated['body_vlink'] = $_POST['body_vlink'];
			$updated['body_alink'] = $_POST['body_alink'];
			$updated['body_hlink'] = $_POST['body_hlink'];
			$updated['tr_color1'] = $_POST['tr_color1'];
			$updated_name['tr_color1_name'] =  $_POST['tr_color1_name'];
			$updated['tr_color2'] = $_POST['tr_color2'];
			$updated_name['tr_color2_name'] = $_POST['tr_color2_name'];
			$updated['tr_color3'] = $_POST['tr_color3'];
			$updated_name['tr_color3_name'] = $_POST['tr_color3_name'];
			$updated['tr_class1'] = $_POST['tr_class1'];
			$updated_name['tr_class1_name'] = $_POST['tr_class1_name'];
			$updated['tr_class2'] = $_POST['tr_class2'];
			$updated_name['tr_class2_name'] = $_POST['tr_class2_name'];
			$updated['tr_class3'] = $_POST['tr_class3'];
			$updated_name['tr_class3_name'] = $_POST['tr_class3_name'];
			$updated['th_color1'] = $_POST['th_color1'];
			$updated_name['th_color1_name'] = $_POST['th_color1_name'];
			$updated['th_color2'] = $_POST['th_color2'];
			$updated_name['th_color2_name'] = $_POST['th_color2_name'];
			$updated['th_color3'] = $_POST['th_color3'];
			$updated_name['th_color3_name'] = $_POST['th_color3_name'];
			$updated['th_class1'] = $_POST['th_class1'];
			$updated_name['th_class1_name'] = $_POST['th_class1_name'];
			$updated['th_class2'] = $_POST['th_class2'];
			$updated_name['th_class2_name'] = $_POST['th_class2_name'];
			$updated['th_class3'] = $_POST['th_class3'];
			$updated_name['th_class3_name'] = $_POST['th_class3_name'];
			$updated['td_color1'] = $_POST['td_color1'];
			$updated_name['td_color1_name'] = $_POST['td_color1_name'];
			$updated['td_color2'] = $_POST['td_color2'];
			$updated_name['td_color2_name'] = $_POST['td_color2_name'];
			$updated['td_color3'] = $_POST['td_color3'];
			$updated_name['td_color3_name'] = $_POST['td_color3_name'];
			$updated['td_class1'] = $_POST['td_class1'];
			$updated_name['td_class1_name'] = $_POST['td_class1_name'];
			$updated['td_class2'] = $_POST['td_class2'];
			$updated_name['td_class2_name'] = $_POST['td_class2_name'];
			$updated['td_class3'] = $_POST['td_class3'];
			$updated_name['td_class3_name'] = $_POST['td_class3_name'];
			$updated['fontface1'] = $_POST['fontface1'];
			$updated_name['fontface1_name'] = $_POST['fontface1_name'];
			$updated['fontface2'] = $_POST['fontface2'];
			$updated_name['fontface2_name'] = $_POST['fontface2_name'];
			$updated['fontface3'] = $_POST['fontface3'];
			$updated_name['fontface3_name'] = $_POST['fontface3_name'];
			$updated['fontsize1'] = (int)$_POST['fontsize1'];
			$updated_name['fontsize1_name'] = $_POST['fontsize1_name'];
			$updated['fontsize2'] = (int)$_POST['fontsize2'];
			$updated_name['fontsize2_name'] = $_POST['fontsize2_name'];
			$updated['fontsize3'] = (int)$_POST['fontsize3'];
			$updated_name['fontsize3_name'] = $_POST['fontsize3_name'];
			$updated['fontcolor1'] = $_POST['fontcolor1'];
			$updated_name['fontcolor1_name'] = $_POST['fontcolor1_name'];
			$updated['fontcolor2'] = $_POST['fontcolor2'];
			$updated_name['fontcolor2_name'] = $_POST['fontcolor2_name'];
			$updated['fontcolor3'] = $_POST['fontcolor3'];
			$updated_name['fontcolor3_name'] = $_POST['fontcolor3_name'];
			$updated['span_class1'] = $_POST['span_class1'];
			$updated_name['span_class1_name'] = $_POST['span_class1_name'];
			$updated['span_class2'] = $_POST['span_class2'];
			$updated_name['span_class2_name'] = $_POST['span_class2_name'];
			$updated['span_class3'] = $_POST['span_class3'];
			$updated_name['span_class3_name'] = $_POST['span_class3_name'];
			$style_id = (int)$_POST['style_id'];
			//
			// Wheeeew! Thank heavens for copy and paste and search and replace :D
			//
			
			if ($mode == "edit") {
			    dibi::update(THEMES_TABLE, $updated)
                    ->where('themes_id = %i', $style_id)
                    ->execute();
				
				//
				// Check if there's a names table entry for this style
				//
				$theme_exists = dibi::select('themes_id')
                    ->from(THEMES_NAME_TABLE)
                    ->where('themes_id = %i', $style_id)
                    ->fetchSingle();

				if ($theme_exists) {
				    dibi::update(THEMES_NAME_TABLE, $updated_name)
                        ->where('themes_id = %i', $style_id)
                        ->execute();
				} else {
                    $updated_name['themes_id'] =  $style_id;

				    dibi::insert(THEMES_NAME_TABLE, $updated_name)->execute();
				}
							
				$message = $lang['Theme_updated'] . "<br /><br />" . sprintf($lang['Click_return_styleadmin'], "<a href=\"" . append_sid("admin_styles.php") . "\">", "</a>") . "<br /><br />" . sprintf($lang['Click_return_admin_index'], "<a href=\"" . append_sid("index.php?pane=right") . "\">", "</a>");

				message_die(GENERAL_MESSAGE, $message);
			} else {
				//
				// First, check if we already have a style by this name
				//
                $theme_check = dibi::select('themes_id')
                    ->from(THEMES_TABLE)
                    ->where('style_name = %s', $updated['style_name'])
                    ->fetch();
				
				if ($theme_check) {
					message_die(GENERAL_ERROR, $lang['Style_exists'], $lang['Error']);
				}

                $style_id = dibi::insert(THEMES_TABLE, $updated)->execute(dibi::IDENTIFIER);
				
				// 
				// Insert names data
				//
                $updated_name['themes_id'] =  $style_id;

                dibi::insert(THEMES_NAME_TABLE, $updated_name)->execute();
				
				$message = $lang['Theme_created'] . "<br /><br />" . sprintf($lang['Click_return_styleadmin'], "<a href=\"" . append_sid("admin_styles.php") . "\">", "</a>") . "<br /><br />" . sprintf($lang['Click_return_admin_index'], "<a href=\"" . append_sid("index.php?pane=right") . "\">", "</a>");

				message_die(GENERAL_MESSAGE, $message);
			}
		} else {
			if ($mode == "edit") {
				$themes_title = $lang['Edit_theme'];
				$themes_explain = $lang['Edit_theme_explain'];
				
				$style_id = (int)$_GET['style_id'];
				
				$selected_names = [];

				// 
				// Fetch the Theme Info from the db
				//
                $selected_values = dibi::select('*')
                    ->from(THEMES_TABLE)
                    ->where('themes_id = %i', $style_id)
                    ->fetch();
				
				//
				// Fetch the Themes Name data
				//
                $selected_names = dibi::select('*')
                    ->from(THEMES_NAME_TABLE)
                    ->where('themes_id = %i', $style_id)
                    ->fetch();

                $selected = array_merge($selected_names->toArray(), $selected_values->toArray());

				$s_hidden_fields = '<input type="hidden" name="style_id" value="' . $style_id . '" />';
            } else {
                $themes_title   = $lang['Create_theme'];
                $themes_explain = $lang['Create_theme_explain'];
            }

            $template->set_filenames(["body" => "admin/styles_edit_body.tpl"]);

            if ($dir = @opendir($phpbb_root_path . 'templates/')) {
                $s_template_select = '<select name="template_name">';

                while ($file = @readdir($dir)) {
                    if (!is_file(phpbb_realpath($phpbb_root_path . 'templates/' . $file)) && !is_link(phpbb_realpath($phpbb_root_path . 'templates/' . $file)) && $file != "." && $file != ".." && $file != "CVS" ) {
                        if ($file == $selected['template_name']) {
                            $s_template_select .= '<option value="' . $file . '" selected="selected">' . $file . "</option>\n";
                        } else {
                            $s_template_select .= '<option value="' . $file . '">' . $file . "</option>\n";
                        }
                    }
				}

				$s_template_select .= '</select>';
			} else {
				message_die(GENERAL_MESSAGE, $lang['No_template_dir']);
			}

			$s_hidden_fields .= '<input type="hidden" name="mode" value="' . $mode . '" />';

			$template->assign_vars(array(
				"L_THEMES_TITLE" => $themes_title,
				"L_THEMES_EXPLAIN" => $themes_explain,
				"L_THEME_NAME" => $lang['Theme_name'],
				"L_TEMPLATE" => $lang['Template'],
				"L_THEME_SETTINGS" => $lang['Theme_settings'],
				"L_THEME_ELEMENT" => $lang['Theme_element'],
				"L_SIMPLE_NAME" => $lang['Simple_name'],
				"L_VALUE" => $lang['Value'],
				"L_STYLESHEET" => $lang['Stylesheet'],
				"L_STYLESHEET_EXPLAIN" => $lang['Stylesheet_explain'],
				"L_BACKGROUND_IMAGE" => $lang['Background_image'],
				"L_BACKGROUND_COLOR" => $lang['Background_color'],
				"L_BODY_TEXT_COLOR" => $lang['Text_color'],
				"L_BODY_LINK_COLOR" => $lang['Link_color'],
				"L_BODY_VLINK_COLOR" => $lang['VLink_color'],
				"L_BODY_ALINK_COLOR" => $lang['ALink_color'],
				"L_BODY_HLINK_COLOR" => $lang['HLink_color'],
				"L_TR_COLOR1" => $lang['Tr_color1'],
				"L_TR_COLOR2" => $lang['Tr_color2'],
				"L_TR_COLOR3" => $lang['Tr_color3'],
				"L_TR_CLASS1" => $lang['Tr_class1'],
				"L_TR_CLASS2" => $lang['Tr_class2'],
				"L_TR_CLASS3" => $lang['Tr_class3'],
				"L_TH_COLOR1" => $lang['Th_color1'],
				"L_TH_COLOR2" => $lang['Th_color2'],
				"L_TH_COLOR3" => $lang['Th_color3'],
				"L_TH_CLASS1" => $lang['Th_class1'],
				"L_TH_CLASS2" => $lang['Th_class2'],
				"L_TH_CLASS3" => $lang['Th_class3'],
				"L_TD_COLOR1" => $lang['Td_color1'],
				"L_TD_COLOR2" => $lang['Td_color2'],
				"L_TD_COLOR3" => $lang['Td_color3'],
				"L_TD_CLASS1" => $lang['Td_class1'],
				"L_TD_CLASS2" => $lang['Td_class2'],
				"L_TD_CLASS3" => $lang['Td_class3'],
				"L_FONTFACE_1" => $lang['fontface1'],
				"L_FONTFACE_2" => $lang['fontface2'],
				"L_FONTFACE_3" => $lang['fontface3'],
				"L_FONTSIZE_1" => $lang['fontsize1'],
				"L_FONTSIZE_2" => $lang['fontsize2'],
				"L_FONTSIZE_3" => $lang['fontsize3'],
				"L_FONTCOLOR_1" => $lang['fontcolor1'],
				"L_FONTCOLOR_2" => $lang['fontcolor2'],
				"L_FONTCOLOR_3" => $lang['fontcolor3'],
				"L_SPAN_CLASS_1" => $lang['span_class1'],
				"L_SPAN_CLASS_2" => $lang['span_class2'],
				"L_SPAN_CLASS_3" => $lang['span_class3'],
				"L_SAVE_SETTINGS" => $lang['Save_Settings'], 
				"THEME_NAME" => $selected['style_name'],
				"HEAD_STYLESHEET" => $selected['head_stylesheet'],
				"BODY_BACKGROUND" => $selected['body_background'],
				"BODY_BGCOLOR" => $selected['body_bgcolor'],
				"BODY_TEXT_COLOR" => $selected['body_text'],
				"BODY_LINK_COLOR" => $selected['body_link'],
				"BODY_VLINK_COLOR" => $selected['body_vlink'],
				"BODY_ALINK_COLOR" => $selected['body_alink'],
				"BODY_HLINK_COLOR" => $selected['body_hlink'],
				"TR_COLOR1" => $selected['tr_color1'],
				"TR_COLOR2" => $selected['tr_color2'],
				"TR_COLOR3" => $selected['tr_color3'],
				"TR_CLASS1" => $selected['tr_class1'],
				"TR_CLASS2" => $selected['tr_class2'],
				"TR_CLASS3" => $selected['tr_class3'],
				"TH_COLOR1" => $selected['th_color1'],
				"TH_COLOR2" => $selected['th_color2'],
				"TH_COLOR3" => $selected['th_color3'],
				"TH_CLASS1" => $selected['th_class1'],
				"TH_CLASS2" => $selected['th_class2'],
				"TH_CLASS3" => $selected['th_class3'],
				"TD_COLOR1" => $selected['td_color1'],
				"TD_COLOR2" => $selected['td_color2'],
				"TD_COLOR3" => $selected['td_color3'],
				"TD_CLASS1" => $selected['td_class1'],
				"TD_CLASS2" => $selected['td_class2'],
				"TD_CLASS3" => $selected['td_class3'],
				"FONTFACE1" => $selected['fontface1'],
				"FONTFACE2" => $selected['fontface2'],
				"FONTFACE3" => $selected['fontface3'],
				"FONTSIZE1" => $selected['fontsize1'],
				"FONTSIZE2" => $selected['fontsize2'],
				"FONTSIZE3" => $selected['fontsize3'],
				"FONTCOLOR1" => $selected['fontcolor1'],
				"FONTCOLOR2" => $selected['fontcolor2'],
				"FONTCOLOR3" => $selected['fontcolor3'],
				"SPAN_CLASS1" => $selected['span_class1'],
				"SPAN_CLASS2" => $selected['span_class2'],
				"SPAN_CLASS3" => $selected['span_class3'],

				"TR_COLOR1_NAME" => $selected['tr_color1_name'],
				"TR_COLOR2_NAME" => $selected['tr_color2_name'],
				"TR_COLOR3_NAME" => $selected['tr_color3_name'],
				"TR_CLASS1_NAME" => $selected['tr_class1_name'],
				"TR_CLASS2_NAME" => $selected['tr_class2_name'],
				"TR_CLASS3_NAME" => $selected['tr_class3_name'],
				"TH_COLOR1_NAME" => $selected['th_color1_name'],
				"TH_COLOR2_NAME" => $selected['th_color2_name'],
				"TH_COLOR3_NAME" => $selected['th_color3_name'],
				"TH_CLASS1_NAME" => $selected['th_class1_name'],
				"TH_CLASS2_NAME" => $selected['th_class2_name'],
				"TH_CLASS3_NAME" => $selected['th_class3_name'],
				"TD_COLOR1_NAME" => $selected['td_color1_name'],
				"TD_COLOR2_NAME" => $selected['td_color2_name'],
				"TD_COLOR3_NAME" => $selected['td_color3_name'],
				"TD_CLASS1_NAME" => $selected['td_class1_name'],
				"TD_CLASS2_NAME" => $selected['td_class2_name'],
				"TD_CLASS3_NAME" => $selected['td_class3_name'],
				"FONTFACE1_NAME" => $selected['fontface1_name'],
				"FONTFACE2_NAME" => $selected['fontface2_name'],
				"FONTFACE3_NAME" => $selected['fontface3_name'],
				"FONTSIZE1_NAME" => $selected['fontsize1_name'],
				"FONTSIZE2_NAME" => $selected['fontsize2_name'],
				"FONTSIZE3_NAME" => $selected['fontsize3_name'],
				"FONTCOLOR1_NAME" => $selected['fontcolor1_name'],
				"FONTCOLOR2_NAME" => $selected['fontcolor2_name'],
				"FONTCOLOR3_NAME" => $selected['fontcolor3_name'],
				"SPAN_CLASS1_NAME" => $selected['span_class1_name'],
				"SPAN_CLASS2_NAME" => $selected['span_class2_name'],
				"SPAN_CLASS3_NAME" => $selected['span_class3_name'],
				
				"S_THEME_ACTION" => append_sid("admin_styles.php"),
				"S_TEMPLATE_SELECT" => $s_template_select,
				"S_HIDDEN_FIELDS" => $s_hidden_fields)
			);
			
			$template->pparse("body");
		}
		break;

	case "export";
		if ($_POST['export_template']) {
			$template_name = $_POST['export_template'];

            $themes = dibi::select('*')
                ->from(THEMES_TABLE)
                ->where('template_name = %s', $template_name)
                ->fetchAll();
			
			if (!count($themes)) {
				message_die(GENERAL_MESSAGE, $lang['No_themes']);
			}
			
			$theme_data = '<?php'."\n\n";
			$theme_data .= "//\n// phpBB 2.x auto-generated theme config file for $template_name\n// Do not change anything in this file!\n//\n\n";

            foreach ($themes as $theme) {
                foreach ($theme as $key => $value) {
                    if (!(int)$key && $key != "0" && $key != "themes_id") {
                        $theme_data .= '$' . $template_name . "[$i]['$key'] = \"" . addslashes($value) . "\";\n";
                    }
                }
                $theme_data .= "\n";
            }
			
			$theme_data .= '?' . '>'; // Done this to prevent highlighting editors getting confused!
			
			@umask(0111);

			$fp = @fopen($phpbb_root_path . 'templates/' . basename($template_name) . '/theme_info.cfg', 'w');

			if (!$fp ) {
				//
				// Unable to open the file writeable do something here as an attempt
				// to get around that...
				//
				$s_hidden_fields = '<input type="hidden" name="theme_info" value="' . htmlspecialchars($theme_data) . '" />';
				$s_hidden_fields .= '<input type="hidden" name="send_file" value="1" /><input type="hidden" name="mode" value="export" />';
				
				$download_form = '<form action="' . append_sid("admin_styles.php") . '" method="post"><input class="mainoption" type="submit" name="submit" value="' . $lang['Download'] . '" />' . $s_hidden_fields;

                $template->set_filenames(["body" => "message_body.tpl"]);

                $template->assign_vars(
                    [
                        "MESSAGE_TITLE" => $lang['Export_themes'],
                        "MESSAGE_TEXT"  => $lang['Download_theme_cfg'] . "<br /><br />" . $download_form
                    ]
                );

                $template->pparse('body');
				exit();
			}

			$result = @fwrite($fp, $theme_data, strlen($theme_data));
			fclose($fp);
			
			$message = $lang['Theme_info_saved'] . "<br /><br />" . sprintf($lang['Click_return_styleadmin'], "<a href=\"" . append_sid("admin_styles.php") . "\">", "</a>") . "<br /><br />" . sprintf($lang['Click_return_admin_index'], "<a href=\"" . append_sid("index.php?pane=right") . "\">", "</a>");

			message_die(GENERAL_MESSAGE, $message);

		} elseif ($_POST['send_file']) {
			
			header("Content-Type: text/x-delimtext; name=\"theme_info.cfg\"");
			header("Content-disposition: attachment; filename=theme_info.cfg");

			echo stripslashes($_POST['theme_info']);
		} else {
            $template->set_filenames(["body" => "admin/styles_exporter.tpl"]);

            if ($dir = @opendir($phpbb_root_path . 'templates/') ) {
				$s_template_select = '<select name="export_template">';
				while ($file = @readdir($dir) ) {
					if (!is_file(phpbb_realpath($phpbb_root_path . 'templates/' . $file)) && !is_link(phpbb_realpath($phpbb_root_path . 'templates/' .$file)) && $file != "." && $file != ".." && $file != "CVS" ) {
						$s_template_select .= '<option value="' . $file . '">' . $file . "</option>\n";
					}
				}

				$s_template_select .= '</select>';

            } else {
                message_die(GENERAL_MESSAGE, $lang['No_template_dir']);
            }

            $template->assign_vars(
                [
                    "L_STYLE_EXPORTER"   => $lang['Export_themes'],
                    "L_EXPORTER_EXPLAIN" => $lang['Export_explain'],
                    "L_TEMPLATE_SELECT"  => $lang['Select_template'],
                    "L_SUBMIT"           => $lang['Submit'],

                    "S_EXPORTER_ACTION" => append_sid("admin_styles.php?mode=export"),
                    "S_TEMPLATE_SELECT" => $s_template_select
                ]
            );

            $template->pparse("body");
			
		}
		break;

	case "delete":
		$style_id = isset($_GET['style_id']) ? (int)$_GET['style_id'] : (int)$_POST['style_id'];
		
		if (!$confirm ) {
			if ($style_id == $board_config['default_style']) {
				message_die(GENERAL_MESSAGE, $lang['Cannot_remove_style']);
			}
			
			$hidden_fields = '<input type="hidden" name="mode" value="'.$mode.'" /><input type="hidden" name="style_id" value="'.$style_id.'" />';
			
			//
			// Set template files
			//
            $template->set_filenames(["confirm" => "admin/confirm_body.tpl"]);

            $template->assign_vars(
                [
                    "MESSAGE_TITLE" => $lang['Confirm'],
                    "MESSAGE_TEXT"  => $lang['Confirm_delete_style'],

                    "L_YES" => $lang['Yes'],
                    "L_NO"  => $lang['No'],

                    "S_CONFIRM_ACTION" => append_sid("admin_styles.php"),
                    "S_HIDDEN_FIELDS"  => $hidden_fields
                ]
            );

            $template->pparse("confirm");

		}
		else
		{
			//
			// The user has confirmed the delete. Remove the style, the style element
			// names and update any users who might be using this style
			//
            dibi::delete(THEMES_TABLE)
                ->where('themes_id = %i', $style_id)
                ->execute();
			
			//
			// There may not be any theme name data so don't throw an error
			// if the SQL dosan't work
			//
            dibi::delete(THEMES_NAME_TABLE)
                ->where('themes_id = %i', $style_id)
                ->execute();

            dibi::update(USERS_TABLE, ['user_style' => $board_config['default_style']])
                ->where('user_style = %i', $style_id)
                ->execute();
			
			$message = $lang['Style_removed'] . "<br /><br />" . sprintf($lang['Click_return_styleadmin'], "<a href=\"" . append_sid("admin_styles.php") . "\">", "</a>") . "<br /><br />" . sprintf($lang['Click_return_admin_index'], "<a href=\"" . append_sid("index.php?pane=right") . "\">", "</a>");

			message_die(GENERAL_MESSAGE, $message);
		}
		break;

	default:
        $styles = dibi::select(['themes_id', 'template_name', 'style_name'])
            ->from(THEMES_TABLE)
            ->orderBy('template_name')
            ->fetchAll();

        $template->set_filenames(["body" => "admin/styles_list_body.tpl"]);

        $template->assign_vars(
            [
                "L_STYLES_TITLE" => $lang['Styles_admin'],
                "L_STYLES_TEXT"  => $lang['Styles_explain'],
                "L_STYLE"        => $lang['Style'],
                "L_TEMPLATE"     => $lang['Template'],
                "L_EDIT"         => $lang['Edit'],
                "L_DELETE"       => $lang['Delete']
            ]
        );

        $i = 0;
        foreach ($styles as $style) {
            $i++;
            // TODO $theme should be $style?
			$row_color = ( !($i % 2) ) ? $theme['td_color1'] : $theme['td_color2'];
			$row_class = ( !($i % 2) ) ? $theme['td_class1'] : $theme['td_class2'];

            $template->assign_block_vars("styles",
                [
                    "ROW_CLASS"     => $row_class,
                    "ROW_COLOR"     => $row_color,
                    "STYLE_NAME"    => $style->style_name,
                    "TEMPLATE_NAME" => $style->template_name,

                    "U_STYLES_EDIT"   => append_sid("admin_styles.php?mode=edit&amp;style_id=" . $style->themes_id),
                    "U_STYLES_DELETE" => append_sid("admin_styles.php?mode=delete&amp;style_id=" . $style->themes_id)
                ]
            );
        }

        $template->pparse("body");
		break;
}

if (empty($_POST['send_file'])) {
	include './page_footer_admin.php';
}

?>