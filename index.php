<?php if(!defined('IS_ADMIN') or !IS_ADMIN) die();

/***************************************************************
* 
* FindUnusedThings Plugin für moziloCMS 2.0
* 
***************************************************************/
class FindUnusedThings extends Plugin {

    private $admin_lang;
    private $unused_search = array();
    private $activ;
    private $typen = array("files","galery","plugins","syntax","template");
    private $blackList = array();
    private $blackListPlugins = array("FindUnusedThings","MetaKeywordsDescription");
    private $blackListFiles = array("searchicon.gif");
    private $find_string = array();
    function getContent() {
        if(!defined("PLUGINADMIN"))
            return null;
        global $CatPage;
        $this->activ = $this->typen[0];

        $dialog = "";
        if(getRequestValue('fut_delete_button','post') == "true") {
            $dialog = $this->delThings();
        }

        if(getRequestValue('actab','get',false) and in_array(getRequestValue('actab','get',false),$this->typen)) {
            $this->activ = getRequestValue('actab','get',false);
        }

        $this->makeBlackList();

        $cats = $CatPage->get_CatArray(true, false);

        $this->makeSearch($cats);

        $this->findThings($cats);

        if(count($this->unused_search) > 0) {
            global $PLUGIN_ADMIN_ADD_HEAD;
            $PLUGIN_ADMIN_ADD_HEAD[] = '<script type="text/javascript" src="'.URL_BASE.PLUGIN_DIR_NAME.'/FindUnusedThings/fut_admin.js"></script>';
            $PLUGIN_ADMIN_ADD_HEAD[] = '<script type="text/javascript" src="'.URL_BASE.ADMIN_DIR_NAME.'/jquery/dialog_prev.js"></script>';
        }

        return $this->tabs().$dialog;
    }

    function makeSearch($cats) {
        global $specialchars;
        global $CatPage ;
        if($this->activ == "files") {
            foreach($cats as $cat) {
                foreach($CatPage->get_FileArray($cat) as $file) {
                    $tmp = $specialchars->rebuildSpecialChars($cat, false, false);
                    if(in_array(FILE_START.$tmp.':'.$file.FILE_END,$this->blackList))
                        continue;
                    $this->unused_search[FILE_START.$tmp.':'.$file.FILE_END] = array($cat,$file);
                }
            }
        } elseif($this->activ == "galery") {
            foreach(getDirAsArray(GALLERIES_DIR_REL,"dir") as $dir) {
                $tmp = $specialchars->rebuildSpecialChars($dir, false, false);
                if(in_array(FILE_START.$tmp.FILE_END,$this->blackList))
                    continue;
                $this->unused_search[FILE_START.$tmp.FILE_END] = GALLERIES_DIR_REL.$dir;
            }
        } elseif($this->activ == "plugins") {
            foreach(getDirAsArray(PLUGIN_DIR_REL,"dir") as $plugin) {
                if(in_array($plugin,$this->blackList))
                    continue;
                $this->unused_search[$plugin] = PLUGIN_DIR_REL.$plugin;
            }
        } elseif($this->activ == "syntax") {
            global $USER_SYNTAX;
            foreach($USER_SYNTAX->toArray() as $syntax => $tmp) {
                if(in_array($syntax,$this->blackList))
                    continue;
                $this->unused_search[$syntax] = $syntax;
            }
        } elseif($this->activ == "template") {
            global $CMS_CONF;
            $tmp_dir = LAYOUT_DIR_NAME."/".$CMS_CONF->get("cmslayout")."/grafiken/";
            foreach(getDirAsArray(BASE_DIR.$tmp_dir,"img") as $img) {
                if(in_array($img,$this->blackList))
                    continue;
                $this->unused_search[$img] = $tmp_dir.$img;
            }
            $tmp_dir = LAYOUT_DIR_NAME."/".$CMS_CONF->get("cmslayout")."/css/";
            foreach(getDirAsArray(BASE_DIR.$tmp_dir,array(".css")) as $css) {
                if(in_array($css,$this->blackList))
                    continue;
                $this->unused_search[$css] = $tmp_dir.$css;
            }
        }
    }

    function makeBlackList() {
        global $specialchars;
        if($this->activ == "plugins")
            $this->blackList = $this->blackListPlugins;
        if($this->activ == "template")
            $this->blackList = $this->blackListFiles;
        $tmp = explode(",",$this->settings->get($this->activ));
        foreach($tmp as $value) {
            if(!empty($value)) {
                if($this->activ == "files")
                    $value = $specialchars->rebuildSpecialChars($value, false, false);
                $this->blackList[] = $value;
            }
        }
    }

    function findThings($cats) {
        $this->findInPages($cats);
        global $CMS_CONF;
        $tmp_dir = BASE_DIR.LAYOUT_DIR_NAME."/".$CMS_CONF->get("cmslayout")."/template.html";
        if(is_file($tmp_dir) and false !== ($tmp_content = file_get_contents($tmp_dir)))
            $this->findInContent($tmp_content,"template");
        if($this->activ == "template")
            $this->findTemplateCSS();
        $tmp_dir = BASE_DIR_CMS.CONF_DIR_NAME."/syntax.conf.php";
        if(is_file($tmp_dir) and false !== ($tmp_content = file_get_contents($tmp_dir)))
            $this->findInContent($tmp_content,"syntax");
    }

    function findTemplateCSS() {
        global $CMS_CONF;
        $tmp_dir = BASE_DIR.LAYOUT_DIR_NAME."/".$CMS_CONF->get("cmslayout")."/css/";
        foreach(getDirAsArray($tmp_dir,array(".css")) as $css) {
            if(false !== ($tmp_content = file_get_contents($tmp_dir.$css))) {
                foreach($this->unused_search as $search => $tmp) {
                    $tmp_search = $search;
                    if(strpos($this->unused_search[$search],"/grafiken/") !== false)
                        $tmp_search = "/grafiken/".$search;
                    if(strpos($tmp_content,$tmp_search) !== false)
                        unset($this->unused_search[$search]);
                }
            }
        }
    }

    function findInPages($cats) {
        if(count($this->unused_search) < 1)
            return;
        global $CatPage ;
        $tmp_content = "";
        foreach($cats as $cat) {
            foreach(getDirAsArray(CONTENT_DIR_REL.$cat,array(EXT_PAGE, EXT_HIDDEN, EXT_DRAFT)) as $page) {
                $tmp_content = $CatPage->get_PageContent($cat,$page);
                $this->findInContent($tmp_content,$cat,$page);
                $tmp_content = "";
            }
        }
    }

    function findInContent($tmp_content,$cat = false,$page = false) {
        if(count($this->unused_search) < 1 or strlen($tmp_content) < 10)
            return;
        foreach($this->unused_search as $search => $art) {
           if($this->activ == "files") {
                if(strpos($tmp_content,$search) !== false)
                    unset($this->unused_search[$search]);
                if(isset($this->unused_search[$search]) and strpos($tmp_content,$this->unused_search[$search][1]) !== false)
                    $this->find_string[$search][] = array($cat,$page);
           } elseif($this->activ == "galery") {
                if(strpos($tmp_content,$search) !== false)
                    unset($this->unused_search[$search]);
           } elseif($this->activ == "template") {
                if(strpos($tmp_content,"/grafiken/".$search) !== false or strpos($tmp_content,"/css/".$search) !== false)
                    unset($this->unused_search[$search]);
           } elseif($this->activ == "plugins") {
                if(strpos($tmp_content,"{".$search."|") !== false or strpos($tmp_content,"{".$search."}") !== false)
                    unset($this->unused_search[$search]);
            } elseif($this->activ == "syntax") {
                if(strpos($tmp_content,"[".$search."|") !== false or strpos($tmp_content,"[".$search."=") !== false)
                    unset($this->unused_search[$search]);
            }
            if(count($this->unused_search) < 1)
                return;
        }
    }

    function delThings() {
        $del_array = getRequestValue('fut_delete','post',false);
        if(!is_array($del_array))
            return;
        global $USER_SYNTAX, $specialchars;
        $error_message = "";
        foreach($del_array as $type => $things_array) {
            foreach($things_array as $things) {
                if($type == "files" or $type == "template") {
                    if(true !== ($error = deleteFile($things)))
                        $error_message .= basename($things)."<br />";
                } elseif($type == "galery" or $type == "plugins") {
                    if(true !== ($error = deleteDir($things."d")))
                        $error_message .= $specialchars->rebuildSpecialChars(basename($things), false, false)."<br />";
                } elseif($type == "syntax") {
                    if(true !== $USER_SYNTAX->delete($things))
                        $error_message .= $things."<br />";
                }
            }
        }
        $dialog = "";
        if(!empty($error_message)) {
            $dialog = '<script language="Javascript" type="text/javascript">/*<![CDATA[*/'
                .'$(function() {'
                .'dialog_open("error_messages",\''.returnMessage(false, "<b>".$this->admin_lang->getLanguageValue("error_".$type)."</b><br /><br />".$error_message).'\');'
                .'});'
                .'/*]]>*/</script>';
        }
        if($type == "files") {
            global $CatPage;
            $CatPage->CatPageClass();
        }
        return $dialog;
    }

    function tabs() {
        # mo-td-content-width ist nötig wegen dialog_iframe_preview() wir setzen denn aber dann auf "auto"
        $html = '<div id="fut-admin" class="mo-td-content-width ui-tabs ui-widget ui-widget-content ui-corner-all mo-ui-tabs" style="padding-right:1em;width: auto;">'
            .'<ul id="js-menu-tabs" class="mo-menu-tabs ui-tabs-nav ui-helper-reset ui-helper-clearfix ui-widget-header ui-corner-top">';
        $tabindex = 0;
        foreach($this->typen as $typ) {
            $activ_tab = ' js-hover-default mo-ui-state-hover';
            if($this->activ == $typ)
                $activ_tab = ' ui-tabs-selected ui-state-active';
            $html .= '<li class="ui-state-default ui-corner-top'.$activ_tab.'"><a href="'.PLUGINADMIN_GET_URL.'&amp;actab='.$typ.'" tabindex="'.($tabindex++).'"><span class="mo-bold">'.$this->admin_lang->getLanguageValue("tab_".$typ).'</span></a></li>';
        }
        $html .= '</ul>'
            .'<div id="fut-things" class="mo-ui-tabs-panel ui-widget-content ui-corner-bottom mo-no-border-top">'
            .'<form name="fut-form" action="'.PLUGINADMIN_GET_URL.'&amp;actab='.$this->activ.'" method="post">'
            .$this->adminTmpl()
            .'</form></div></div>';
        return $html;
    }

    function adminTemplateFilesTmpl() {
        global $specialchars;
        $tmp = array();
        $tmp1 = array();
        $tmp2 = array();
        foreach($this->unused_search as $key => $var) {
            if(strpos($var,"/css/") !== false)
                $tmp2[$key] = $var;
            else
                $tmp1[$key] = $var;
        }
        if(count($tmp1) > 0) {
            ksort($tmp1);
            $tmp["text_template_img"] = $tmp1;
        }
        if(count($tmp2) > 0) {
            ksort($tmp2);
            $tmp["text_template_css"] = $tmp2;
        }
        $html ="";
        foreach($tmp as $title => $files) {
            $html .= '<li class="mo-li ui-widget-content ui-corner-all ui-helper-clearfix">'
                .'<div class="mo-bold mo-padding-plugin">'.$this->admin_lang->getLanguageValue($title).'</div>'
                .'<ul class="mo-in-ul-ul">';
            foreach($files as $file => $filepfad) {
                $html .= '<li class="mo-in-ul-li mo-middle ui-widget-content ui-corner-all ui-helper-clearfix">'
                    .'<div class="mo-in-li-l"><a title="'.$file.'" href="'.URL_BASE.$specialchars->replaceSpecialChars($filepfad,true).'"  class="fut-preview"><img class="fu-ext-imgs fu-ext-'.$this->mimeType($file).'" src="'.ICON_URL_SLICE.'"></a>'
                    .'<span class="fut-padding-left">'.$file.'</span></div>'
                    .'<div class="align-right mo-in-li-r"><input type="checkbox" class="mo-checkbox fut-checkbox fut-plugin-del" name="fut_delete[files][]" value="'.BASE_DIR.$filepfad.'" /></div>';
            }
            $html .= '</ul></li>';
        }
        return $html;
    }


    function adminFilesTmpl() {
        global $CatPage,$specialchars;
        $tmp = array();
        $tmp1 = array();
        foreach($this->unused_search as $key => $var) {
            $tmp[$var[0]][] = $var[1];
            if(isset($this->find_string[$key]))
                $tmp1[$var[0]][$var[1]] = $this->find_string[$key];
        }
        ksort($tmp);
        $html ="";
        foreach($tmp as $cat => $files) {
            $html .= '<li class="mo-li ui-widget-content ui-corner-all ui-helper-clearfix">'
                .'<div class="mo-bold mo-padding-plugin">'.$specialchars->rebuildSpecialChars($cat, false, false).'</div>'
                .'<ul class="mo-in-ul-ul">';
            sort($files);
            foreach($files as $file) {
                $title = $specialchars->rebuildSpecialChars($file, false, true);
                $html .= '<li class="mo-in-ul-li mo-middle ui-widget-content ui-corner-all ui-helper-clearfix">'
                    .'<div class="mo-in-li-l"><a title="'.$title.'" href="'.$CatPage->get_srcFile($cat,$file).'"  class="fut-preview"><img class="fu-ext-imgs fu-ext-'.$this->mimeType($title).'" src="'.ICON_URL_SLICE.'"></a>'
                    .'<span class="fut-padding-left">'.$title.'</span></div>'
                    .'<div class="align-right mo-in-li-r"><input type="checkbox" class="mo-checkbox fut-checkbox fut-plugin-del" name="fut_delete[files][]" value="'.$CatPage->get_pfadFile($cat,$file).'" /></div>';
                    if(isset($tmp1[$cat][$file])) {
                        $html .= '<div class="fut-as-string-box">'
                                .$this->admin_lang->getLanguageValue("as_string_text")."</div>"
                                .'<ul>';
                        foreach($tmp1[$cat][$file] as $find) {
                            $html .= '<li>';
                            if(!$find[1] and ($find[0] == "syntax" or $find[0] == "template"))
                                $html .= $this->admin_lang->getLanguageValue("as_string_".$find[0])."<br />";
                            else
                                $html .= $this->admin_lang->getLanguageValue("as_string_catpage",$CatPage->get_HrefText($find[0],false),$CatPage->get_HrefText($find[0],$find[1]))."<br />";
                            $html .= '</li>';
                        }
                    $html .= '</ul>';
                    }
                $html .= '</li>';
            }
            $html .= '</ul></li>';
        }
        return $html;
    }

    function adminTmpl() {
        $html = '<ul class="mo-ul">'
            .'<li class="mo-li ui-widget-content ui-corner-all mo-margin-bottom">'
            .'<div class="mo-li-head-tag mo-tag-height-from-icon mo-li-head-tag-no-ul mo-middle ui-state-default ui-corner-top ui-helper-clearfix">'
                .'<div class="mo-in-li-l"><span class="mo-bold mo-padding-left">'.$this->admin_lang->getLanguageValue("text_".$this->activ).'</span></div>';
        if(count($this->unused_search) > 0) {
            $html .= '<div class="align-right mo-in-li-r"><button type="submit" name="fut_delete_button" value="true" class="fut-icons-button mo-icons-icon mo-icons-delete">&nbsp;</button>'
                .'<input type="checkbox" class="mo-checkbox fut-plugin-del-all" /></div>';
        }
        $html .= '</div>'
            .'</li>';
        if(count($this->unused_search) > 0) {
            if($this->activ == "files") {
                $html .= $this->adminFilesTmpl();
            } elseif($this->activ == "template") {
                $html .= $this->adminTemplateFilesTmpl();
            } else {
                ksort($this->unused_search);
                foreach($this->unused_search as $key => $value) {
                    $html .= '<li class="mo-in-ul-li mo-inline ui-widget-content ui-corner-all ui-helper-clearfix">'
                        .'<div class="mo-bold mo-in-li-l">'.str_replace(array(FILE_START,FILE_END),"",$key).'</div>'
                        .'<div class="align-right mo-in-li-r"><input type="checkbox" class="mo-checkbox fut-plugin-del" name="fut_delete['.$this->activ.'][]" value="'.$value.'" /></div>'
                    .'</li>';
                }
            }
        } else {
            $html .= '<li class="mo-in-ul-li ui-widget-content ui-corner-all ui-helper-clearfix">'
                .$this->admin_lang->getLanguageValue("no_things")
                .'</li>';
        }
        return $html.'</ul>';
    }

    function mimeType($file) {
        $ext = strtolower(substr($file,strrpos($file,".")));
        $img = "none";
            if($ext == ".png" or $ext == ".gif" or $ext == ".jpg" or $ext == ".jpeg")
                $img = "img";
            else if($ext == ".doc" or $ext == ".odf")
                $img = "doc";
            else if($ext == ".mpg" or $ext == ".mov" or $ext == ".flv")
                $img = "mov";
            else if($ext == ".pdf")
                $img = "pdf";
            else if($ext == ".txt" or $ext == ".css")
                $img = "txt";
            else if($ext == ".mp3" or $ext == ".mp4" or $ext == ".wav")
                $img = "wav";
            else if($ext == ".zip" or $ext == ".gzip" or $ext == ".gz")
                $img = "zip";
            else if($ext == ".iso")
                $img = "iso";
        return $img;
    }

    function getConfig() {
        $config = array();
        $config["--admin~~"] = array(
            "buttontext" => $this->admin_lang->getLanguageValue("admin_button"),
            "description" => $this->admin_lang->getLanguageValue("admin_text"),
            "datei_admin" => "index.php"
        );

        global $specialchars;
        global $CatPage ;
        global $USER_SYNTAX;
        global $CMS_CONF;

        $tmp_array = array();
        if(CMSREVISION > 2) {
            # ab CMSREVISION 3 gibts optgroup
            foreach($CatPage->get_CatArray(true, false) as $cat) {
                $tmp = $specialchars->rebuildSpecialChars($cat, false, true);
                $tmp_array[$tmp] = array();
                foreach($CatPage->get_FileArray($cat) as $file) {
                    $tmp_array[$tmp][FILE_START.$cat.':'.$file.FILE_END] = $file;
                }
            }
        } else {
            # bei CMSREVISION < 3 gibts noch keine optgroup
            foreach($CatPage->get_CatArray(true, false) as $cat) {
                $tmp = $specialchars->rebuildSpecialChars($cat, false, true)."/ ";
                foreach($CatPage->get_FileArray($cat) as $file) {
                    $tmp_array[FILE_START.$cat.':'.$file.FILE_END] = $tmp.$file;
                    $tmp = "&nbsp;&bull;&nbsp;&nbsp;&nbsp;";
                }
            }
        }
        $config['files'] = array(
            "type" => "select",
            "description" => $this->admin_lang->getLanguageValue("settings_files"),
            "descriptions" => $tmp_array,
            "multiple" => "true"
            );

        $tmp_array = array();
        foreach(getDirAsArray(GALLERIES_DIR_REL,"dir") as $dir) {
            $tmp = $specialchars->rebuildSpecialChars($dir, false, false);
            $tmp_array[FILE_START.$tmp.FILE_END] = $tmp;
        }

        ksort($tmp_array);
        $config['galery'] = array(
            "type" => "select",
            "description" => $this->admin_lang->getLanguageValue("settings_galery"),
            "descriptions" => $tmp_array,
            "multiple" => "true"
            );

        $tmp_array = array();
        foreach(getDirAsArray(PLUGIN_DIR_REL,"dir") as $plugin) {
            if(in_array($plugin,$this->blackListPlugins))
                continue;
            $tmp_array[$plugin] = $plugin;
        }

        ksort($tmp_array);
        $config['plugins'] = array(
            "type" => "select",
            "description" => $this->admin_lang->getLanguageValue("settings_plugins"),
            "descriptions" => $tmp_array,
            "multiple" => "true"
            );

        $tmp_array = array();
        foreach($USER_SYNTAX->toArray() as $syntax => $tmp) {
            $tmp_array[$syntax] = $syntax;
        }

        ksort($tmp_array);
        $config['syntax'] = array(
            "type" => "select",
            "description" => $this->admin_lang->getLanguageValue("settings_syntax"),
            "descriptions" => $tmp_array,
            "multiple" => "true"
            );

        $tmp_array = array();
        $tmp_files = getDirAsArray(BASE_DIR.LAYOUT_DIR_NAME."/".$CMS_CONF->get("cmslayout")."/grafiken/","img");
        sort($tmp_files);
        foreach($tmp_files as $img) {
            if(in_array($img,$this->blackListFiles))
                continue;
            if(CMSREVISION > 2)
                $tmp_array["grafiken"][$img] = $img;
            else
                $tmp_array[$img] = $img;
        }
        $tmp_files = getDirAsArray(BASE_DIR.LAYOUT_DIR_NAME."/".$CMS_CONF->get("cmslayout")."/css/",array(".css"));
        sort($tmp_files);
        foreach($tmp_files as $css) {
            if(in_array($css,$this->blackListFiles))
                continue;
            if(CMSREVISION > 2)
                $tmp_array["css"][$css] = $css;
            else
                $tmp_array[$css] = $css;
        }

        $config['template'] = array(
            "type" => "select",
            "description" => $this->admin_lang->getLanguageValue("settings_template"),
            "descriptions" => $tmp_array,
            "multiple" => "true"
        );
        $tmp_hr = '<div style="clear:both;padding-top:1px;padding-bottom:3px;"><hr style="margin:0;" /></div>';
        $config['--template~~'] = '<div style="padding-bottom:.5em">'.$this->admin_lang->getLanguageValue("settings_title").'</div>'
                    .'<div style="padding-left:1.5em">'
                        .'<div style="padding-top:3px;" class="mo-in-li-l">{files_description}</div>'
                        .'<div class="mo-in-li-r">{files_select}</div>'
                        .$tmp_hr
                        .'<div style="padding-top:3px;" class="mo-in-li-l">{galery_description}</div>'
                        .'<div class="mo-in-li-r">{galery_select}</div>'
                        .$tmp_hr
                        .'<div style="padding-top:3px;" class="mo-in-li-l">{plugins_description}</div>'
                        .'<div class="mo-in-li-r">{plugins_select}</div><br />'
                        .$tmp_hr
                        .'<div style="padding-top:3px;" class="mo-in-li-l">{syntax_description}</div>'
                        .'<div class="mo-in-li-r">{syntax_select}</div>'
                        .$tmp_hr
                        .'<div style="padding-top:3px;" class="mo-in-li-l">{template_description}</div>'
                        .'<div class="mo-in-li-r">{template_select}</div>'
                    .'</div>';
        return $config;
    }

    function getInfo() {
        global $ADMIN_CONF;

        $this->admin_lang = new Language(PLUGIN_DIR_REL."FindUnusedThings/sprachen/admin_language_".$ADMIN_CONF->get("language").".txt");
        $admin_info = @file_get_contents(PLUGIN_DIR_REL."FindUnusedThings/sprachen/admin_info_".$ADMIN_CONF->get("language").".txt");

        $info = array(
            "<b>FindUnusedThings</b> Revision: 3",
            "2.0",
            $admin_info,
            "stefanbe",
            array("http://www.mozilo.de/forum/index.php?action=media","Templates und Plugins"),
            ""
        );
        return $info;
    }
}

?>
