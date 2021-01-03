<?php

/**

 * Module for personal information

 *

 * This file is used for user management - either personal or by an administrator

 *

 * Roadmap: (search for the following titles)

 * - Check the user type and define the currentUser instance

 * - [HCD] Access Control

 * - Tabberajax calculation

 * - [HCD] Evaluation Management -> exit

 * - Add User or Edit User

 * --- Create $editedUser, [HCD] $editedEmployee in case of submit

 *

 * --- Submit posted forms: lessons, courses, groups, avatar, [HCD] job descriptions, [HCD] skills

 * --- Create the add/edit user form

 * --- Submit posted form: personal information

 *

 * --- [HCD] Retrieve all Employee information to appear on the form: job descriptions, skills, evaluations

 * --- [HCD] Include file manager

 * --- Retrieve all User information to appear on the form: personal information, lessons, courses, certificates, groups

 * -

 *

 * @package eFront

 * @version 1.0

 */
//This file cannot be called directly, only included.
if (str_replace(DIRECTORY_SEPARATOR, "/", __FILE__) == $_SERVER['SCRIPT_FILENAME']) {
 exit;
}
!isset($currentUser -> coreAccess['users']) || $currentUser -> coreAccess['users'] == 'change' ? $_change_ = 1 : $_change_ = 0;
$smarty -> assign("_change_", $_change_);
if ($currentUser -> user['user_type'] == 'administrator' && $_GET['ctg'] == 'personal') {
 eF_redirect(basename($_SERVER['PHP_SELF']).'?ctg=users&edit_user='.$currentUser -> user['login'].($_GET['op'] ? "&op=".$_GET['op'] : ""));
}
$smarty -> assign('T_ORIGINAL_CTG',$ctg);
//error_reporting(E_ALL);
//echo "<pre>";print_r($_POST);print_r($_GET);
//print_r($_FILES);
$loadScripts[] = 'includes/personal';
// Set facebook template variables
if ($GLOBALS['configuration']['social_modules_activated'] & FB_FUNC_CONNECT) {
 if (isset($_SESSION['facebook_user']) && $_SESSION['facebook_user']) {
  $smarty -> assign("T_OPEN_FACEBOOK_SESSION",1);
  $smarty -> assign("T_FACEBOOK_API_KEY", $GLOBALS['configuration']['facebook_api_key']);
  $smarty -> assign("T_FACEBOOK_SHOULD_UPDATE_STATUS", $_SESSION['facebook_can_update']);
 }
 $smarty -> assign("T_FACEBOOK_ENABLED", 1);
}
/***************************************************************/
/*** Check the user type and define the currentUser instance ***/
/***************************************************************/
if (isset($currentUser -> login) && $_SESSION['s_password']) {
 try {
  // The factory takes care for the definition of the HCD user type in $currentUser -> aspects['hcd']
  if (!($currentUser instanceOf EfrontUser)) {
   $currentUser = EfrontUserFactory :: factory($currentUser -> login);
  }
  $currentEmployee = $currentUser -> aspects['hcd'];
 } catch (EfrontException $e) {
  $message = $e -> getMessage().' ('.$e -> getCode().')';
  eF_redirect("index.php?message=".urlencode($message)."&message_type=failure");
  exit;
 }
} else {
 eF_redirect("index.php?message=".urlencode(_YOUCANNOTACCESSTHISPAGE)."&message_type=failure");
 exit;
}
if (isset($_GET['add_evaluation']) || isset($_GET['edit_evaluation'])) {
 if (isset($_GET['add_evaluation'])) {
  $form = new HTML_QuickForm("evaluations_form", "post", basename($_SERVER['PHP_SELF'])."?ctg=users&edit_user=".$_GET['edit_user']."&add_evaluation=1", "", null, true);
 } else {
  $form = new HTML_QuickForm("evaluations_form", "post", basename($_SERVER['PHP_SELF'])."?ctg=users&edit_user=".$_GET['edit_user']."&edit_evaluation=".$_GET['edit_evaluation'], "", null, true);
 }
 // Hidden for maintaining the previous_url value
 $form -> addElement('hidden', 'previous_url', null, 'id="previous_url"');
 $previous_url = getenv('HTTP_REFERER');
 if ($position = strpos($previous_url, "&message")) {
  $previous_url = substr($previous_url, 0, $position);
 }
 $form -> setDefaults(array( 'previous_url' => $previous_url));
 $load_editor = true;
 $form -> addElement('textarea', 'specification', _EVALUATIONCOMMENT, 'class = "simpleEditor inputTextArea" style = "width:100%;height:14em;"');
 if (isset($_GET['edit_evaluation'])) {
  $evaluations = eF_getTableData("module_hcd_events","*","event_ID = '".$_GET['edit_evaluation']."'");
  if ($currentUser -> getType() != 'administrator' && ($evaluations[0]['author'] != $currentUser -> login)) {
   $message = _YOUCANNOTEDITSOMEELSESEVALUATION;
   $message_type = 'failure';
   eF_redirect("".basename($form->exportValue('previous_url'))."&message=". $message . "&message_type=" . $message_type . "&tab=evaluations");
   //eF_redirect("".$_SERVER['HTTP_REFERER']."&tab=evaluations&message=". $message . "&message_type=" . $message_type);
   exit;
  }
  $form -> setDefaults( array('specification' => $evaluations[0]['specification']));
 }
 //$form -> addRule('specification', _THEFIELD.' '._EVALUATIONCOMMENT .' '._ISMANDATORY, 'required', null, 'client');//Commented out because it creates problem with tinymce's simpleEditor
 $form -> addElement('submit', 'submit_evaluation_details', _SUBMIT, 'class = "flatButton" tabindex="2"');
 $renderer = new HTML_QuickForm_Renderer_ArraySmarty($smarty);
 $renderer -> setRequiredTemplate(
        '{$html}{if $required}
            &nbsp;<span class = "formRequired">*</span>
        {/if}');
 /*****************************************************

	 EVALUATION DATA SUBMISSION

	 **************************************************** */
 if ($form -> isSubmitted()) {
  if ($form -> validate()) {
   $evaluation_content = array('specification' => $form->exportValue('specification'),
                                        'event_code' => 10,
                                        'users_login' => $_GET['edit_user'],
                                        'author' => $currentUser -> login,
                                        'timestamp' => time());
   if (isset($_GET['add_evaluation'])) {
    if ($ok = eF_insertTableData("module_hcd_events", $evaluation_content)) {
     $message = _SUCCESSFULLYCREATEDEVALUATION;
     $message_type = 'success';
    }
    else {
     $message = _EVALUATIONCOULDNOTBECREATED.": ".$ok;
     $message_type = 'failure';
    }
   } elseif (isset($_GET['edit_evaluation'])) {
    eF_updateTableData("module_hcd_events", $evaluation_content, "event_ID = '" . $_GET['edit_evaluation']. "'");
    $message = _EVALUATIONDATAUPDATED;
    $message_type = 'success';
   }
   // A little risky, but i think that all urls have sth like ?ctg= , so np
   //eF_redirect("".basename($form->exportValue('previous_url'))."&message=". $message . "&message_type=" . $message_type . "&tab=evaluations");
   //exit;
  }
 }
 $form -> setJsWarnings(_BEFOREJAVASCRIPTERROR, _AFTERJAVASCRIPTERROR);
 $form -> setRequiredNote(_REQUIREDNOTE);
 $form -> accept($renderer);
 $smarty -> assign('T_EVALUATIONS_FORM', $renderer -> toArray());
} else {
 /****************************************************************************************************************************************************/
 /************************************************* ADD USER OR EDIT USER ****************************************************************************/
 /****************************************************************************************************************************************************/
 /************************************************* Create $editedUser, [HCD] $editedEmployee in case of submit *************************************************/
 //If the user is not specified through the get parameter, it means that a user with no priviledges is changing his own personal settings.
 if (!isset($_GET['edit_user']) && !isset($_GET['add_user'])) {
  $_GET['edit_user'] = $currentUser -> login;
  $editedUser = $currentUser;
  $editedEmployee = $currentUser -> aspects['hcd'];
 } else if (isset($_GET['edit_user'])) {
  // The $editedUser object will be set here if a user is changing his own data. Otherwise, it will be created here for the user under edition
  if (!isset($editedUser)) {
    $editedUser = EfrontUserFactory :: factory($_GET['edit_user']); //new EfrontUser();
    $editedEmployee = $editedUser -> aspects['hcd'];
  }
 }
 $smarty -> assign("T_LOGIN", $_GET['edit_user']);
 $smarty -> assign("T_EDITEDUSER", $editedUser);
 //Set the avatar
 try {
  $avatarsFileSystemTree = new FileSystemTree(G_SYSTEMAVATARSPATH);
  foreach (new EfrontFileTypeFilterIterator(new EfrontFileOnlyFilterIterator(new EfrontNodeFilterIterator(new RecursiveIteratorIterator($avatarsFileSystemTree -> tree, RecursiveIteratorIterator :: SELF_FIRST))), array('png')) as $key => $value) {
   $systemAvatars[basename($key)] = basename($key);
  }
  $smarty -> assign("T_SYSTEM_AVATARS", $systemAvatars);
 } catch (Exception $e) {
  $smarty -> assign("T_EXCEPTION_TRACE", $e -> getTraceAsString());
  $message = $e -> getMessage().' ('.$e -> getCode().') &nbsp;<a href = "javascript:void(0)" onclick = "eF_js_showDivPopup(\''._ERRORDETAILS.'\', 2, \'error_details\')">'._MOREINFO.'</a>';
 }
 /**

	 * The avatar form has changed since 3.6.0.

	 * In the personal mode it is a part of the user profile tab and contains other information as well

	 * which are submitted through it.

	 */
 if ($editedUser -> user['login'] == $currentUser -> user['login']) { //The user is editing himself
  if ($currentUser -> getType() == "administrator") {
   $form = new HTML_QuickForm("set_avatar_form", "post", basename($_SERVER['PHP_SELF'])."?ctg=users&edit_user=".$currentUser -> user['login']."&tab=my_profile&op=account", "", null, true);
   $baseUrl = "ctg=users&edit_user=".$currentUser -> user['login'];
  } else {
   $form = new HTML_QuickForm("set_avatar_form", "post", basename($_SERVER['PHP_SELF'])."?ctg=personal&tab=my_profile&op=account", "", null, true);
   $baseUrl = "ctg=personal";
  }
  $smarty -> assign("T_PERSONAL_CTG", 1);
  if ($GLOBALS['configuration']['social_modules_activated'] > 0) {
   $personal_profile_form = 1;
   $smarty -> assign("T_SOCIAL_INTERFACE", 1);
   $systemAvatars = array_merge(array("" => ""), $systemAvatars);
  }
 } else { //The user is being edited by the admin
  $form = new HTML_QuickForm("set_avatar_form", "post", basename($_SERVER['PHP_SELF'])."?ctg=users&edit_user=".$editedUser -> user['login'], "", null, true);
  if ($GLOBALS['configuration']['social_modules_activated'] > 0) {
   $personal_profile_form = 1;
   $smarty -> assign("T_SOCIAL_INTERFACE", 1);
   $systemAvatars = array_merge(array("" => ""), $systemAvatars);
  }
  $baseUrl = "ctg=users&edit_user=".$editedUser -> user['login'];
 }
 $form -> registerRule('checkParameter', 'callback', 'eF_checkParameter'); //Register this rule for checking user input with our function, eF_checkParameter
 $form -> addElement('file', 'file_upload', _IMAGEFILE, 'class = "inputText"');
 $form -> addElement('advcheckbox', 'delete_avatar', _DELETECURRENTAVATAR, null, 'class = "inputCheckbox"', array(0, 1));
 $form -> addElement('select', 'system_avatar' , _ORSELECTONEFROMLIST, $systemAvatars, "id = 'select_avatar'");
 $form -> setMaxFileSize(FileSystemTree :: getUploadMaxSize() * 1024); //getUploadMaxSize returns size in KB
 // Distinguishing between personal and other user administrator
 if ($ctg == "personal") {
  if (!isset($_GET['op'])) {
   $_GET['op'] = 'dashboard';
  }
  if ($currentUser -> coreAccess['dashboard'] == 'hidden') {
   $options = array( array('image' => '16x16/generic.png', 'title' => _MYACCOUNT, 'link' => basename($_SERVER['PHP_SELF']).'?'.$baseUrl.'&op=account', 'selected' => isset($_GET['op']) && $_GET['op'] == 'account' ? true : false));
  } else {
   $options = array( array('image' => '16x16/home.png', 'title' => _DASHBOARD, 'link' => basename($_SERVER['PHP_SELF']).'?'.$baseUrl.'&op=dashboard', 'selected' => isset($_GET['op']) && $_GET['op'] == 'dashboard' ? true : false),
       array('image' => '16x16/generic.png', 'title' => _MYACCOUNT, 'link' => basename($_SERVER['PHP_SELF']).'?'.$baseUrl.'&op=account', 'selected' => isset($_GET['op']) && $_GET['op'] == 'account' ? true : false));
  }
  if ($currentUser -> getType() != "administrator") {
   $options[] = array('image' => '16x16/user_timeline.png', 'title' => _MYSTATUS, 'link' => basename($_SERVER['PHP_SELF']).'?'.$baseUrl.'&op=status' , 'selected' => isset($_GET['op']) && $_GET['op'] == 'status' ? true : false);
  }
  $titles = array ( "account" => array("edituser" => _MYSETTINGS,
            "profile" => _MYPROFILE,
            "mapped" => _MAPPEDACCOUNTS,
            "placements" => _MYPLACEMENTS,
            "history" => _MYHISTORY,
            "files" => _MYFILES,
            "payments" => _PAYPALMYTRANSACTIONS),
        "status" => array("lessons" => _MYLESSONS,
           "courses" => _MYCOURSES,
             "groups" => _MYGROUPS,
           "certifications"=> _MYCERTIFICATIONS));
 } else {
  if (!isset($_GET['op'])) {
   $_GET['op'] = 'account';
  }
  $options = array(array('image' => '16x16/generic.png', 'title' => _EDITUSER, 'link' => basename($_SERVER['PHP_SELF']).'?'.$baseUrl.'&op=account', 'selected' => isset($_GET['op']) && $_GET['op'] == 'account' ? true : false),
  array('image' => '16x16/user_timeline.png', 'title' => _LEARNINGSTATUS, 'link' => basename($_SERVER['PHP_SELF']).'?'.$baseUrl.'&op=status' , 'selected' => isset($_GET['op']) && $_GET['op'] == 'status' ? true : false));
  $titles = array ( "account" => array("edituser" => _EDITUSER,
            "profile" => _USERPROFILE,
            "mapped" => _ADDITIONALACCOUNTS,
            "placements" => _PLACEMENTS,
            "history" => _HISTORY,
            "files" => _FILERECORD,
            "payments" => _PAYMENTS),
        "status" => array("lessons" => _LESSONS,
           "courses" => _COURSES,
             "groups" => _GROUPS,
           "certifications"=> _CERTIFICATIONS));
 }
 $smarty -> assign("T_OP",$_GET['op']);
 $smarty -> assign("T_TABLE_OPTIONS", $options);
 $smarty -> assign("T_TITLES", $titles);
 // If in personal mode then include the user profile fields
 if ($personal_profile_form) {
  /*

		 //This page has a file manager, so bring it on with the correct options

		 $basedir    = $currentUser -> getDirectory();

		 //Default options for the file manager

		 $options = array('delete'        => false,

		 'edit'          => false,

		 'share'         => false,

		 'upload'        => false,

		 'create_folder' => false,

		 'zip'           => false,

		 'lessons_ID'    => false,

		 'metadata'      => 0);

		 //Default url for the file manager

		 $url = basename($_SERVER['PHP_SELF']).'?ctg=users&edit_user='.$_GET['edit_user'];

		 $extraFileTools = array(array('image' => 'images/16x16/arrow_right.png', 'title' => _INSERTEDITOR, 'action' => 'insert_editor'));



		 include "file_manager.php";

		 */
  if (!($GLOBALS['configuration']['social_modules_activated'] & SOCIAL_FUNC_USERSTATUS)) {
   if ($currentUser -> coreAccess['dashboard'] == 'hidden') {
    $smarty -> assign("T_HIDE_USER_STATUS", 1);
   }
  }
  $form -> addElement('textarea', 'short_description', _SHORTDESCRIPTIONCV, 'class = "inputContentTextarea simpleEditor" style = "width:100%;height:14em;"'); //The unit content itself
  if ($_GET['op'] == 'account') { //normally editor is not needed with op='dashboard' makriria 7/6/2010
   $load_editor = true;
  }
  $form -> setDefaults(array( 'short_description' => $editedUser -> user['short_description']));
 }
 //Get the dashboard innertables
 if (!isset($_GET['add_user']) && ($editedUser -> login == $currentUser -> login)) {
  $loadScripts[] = 'scriptaculous/dragdrop';
  require_once 'social.php';
 }
 //$form -> setMaxFileSize(FileSystemTree :: getUploadMaxSize() * 1024);            //getUploadMaxSize returns size in KB
 if ((isset($currentUser -> coreAccess['users']) && $currentUser -> coreAccess['users'] != 'change') || (isset($currentUser -> coreAccess['dashboard']) && $currentUser -> coreAccess['dashboard'] != 'change')) {
  $form -> freeze();
 } else {
  if ($personal_profile_form) {
   $form -> addElement('submit', 'submit_upload_file', _APPLYPROFILECHANGES, 'class = "flatButton"');
  } else {
   $form -> addElement('submit', 'submit_upload_file', _APPLYAVATARCHANGES, 'class = "flatButton"');
  }
  if ($form -> isSubmitted() && $form -> validate()) {
   $avatarDirectory = G_UPLOADPATH.$editedUser -> login.'/avatars';
   if (!is_dir($avatarDirectory)) {
    mkdir($avatarDirectory);
   }
   try {
    try {
     $filesystem = new FileSystemTree($avatarDirectory);
     $uploadedFile = $filesystem -> uploadFile('file_upload', $avatarDirectory);
     // Normalize avatar picture to 150xDimY or DimX x 100
     eF_normalizeImage($avatarDirectory . "/" . $uploadedFile['name'], $uploadedFile['extension'], 150, 100);
     $editedUser -> user['avatar'] = $uploadedFile['id'];
     EfrontEvent::triggerEvent(array("type" => EfrontEvent::AVATAR_CHANGE, "users_LOGIN" => $editedUser -> user['login'], "users_name" => $editedUser->user['name'], "users_surname" => $editedUser->user['surname'], "lessons_ID" => 0, "lessons_name" => "", "entity_ID" => $editedUser -> user['avatar']));
     if ($personal_profile_form) {
      $editedUser -> user['short_description'] = $form ->exportValue('short_description');
      EfrontEvent::triggerEvent(array("type" => EfrontEvent::PROFILE_CHANGE, "users_LOGIN" => $editedUser -> user['login'], "users_name" => $editedUser->user['name'], "users_surname" => $editedUser->user['surname'], "lessons_ID" => 0, "lessons_name" => ""));
      $message = _SUCCESFULLYUPDATEDPROFILE;
     } else {
      $message = _SUCCESFULLYSETAVATAR;
     }
     $message_type = 'success';
     $editedUser -> persist();
    } catch (Exception $e) {
     if ($e -> getCode() != UPLOAD_ERR_NO_FILE) {
      throw $e;
     }
     if ($form -> exportValue('delete_avatar')) {
      $selectedAvatar = 'unknown_small.png';
     } else {
      if (!$personal_profile_form || $form -> exportValue('system_avatar') != "") {
       $selectedAvatar = $form -> exportValue('system_avatar');
      }
     }
     if (isset($selectedAvatar)) {
      $selectedAvatar = $avatarsFileSystemTree -> seekNode(G_SYSTEMAVATARSPATH.$selectedAvatar);
      $newList = FileSystemTree :: importFiles($selectedAvatar['path']); //Import the file to the database, so we can access it with view_file
      $editedUser -> user['avatar'] = key($newList);
      EfrontEvent::triggerEvent(array("type" => EfrontEvent::AVATAR_CHANGE, "users_LOGIN" => $editedUser -> user['login'], "users_name" => $editedUser->user['name'], "users_surname" => $editedUser->user['surname'], "lessons_ID" => 0, "lessons_name" => "", "entity_ID" => $editedUser -> user['avatar']));
      $needed_reload = 1;
      $message = _SUCCESFULLYSETAVATAR; // in case we have simultaneous changes in profile and avatar this value will be overwritten
     }
     if ($personal_profile_form) {
      if (!$needed_reload) {
       $no_reload_needed = 1;
      }
      $editedUser -> user['short_description'] = $form ->exportValue('short_description');
      EfrontEvent::triggerEvent(array("type" => EfrontEvent::PROFILE_CHANGE, "users_LOGIN" => $editedUser -> user['login'], "users_name" => $editedUser->user['name'], "users_surname" => $editedUser->user['surname'], "lessons_ID" => 0, "lessons_name" => ""));
      $message = _SUCCESFULLYUPDATEDPROFILE;
     }
     $editedUser -> persist();
     $message_type = 'success';
    }
    if ($editedUser -> login == $currentUser -> login && !$no_reload_needed) {
     $smarty -> assign("T_REFRESH_SIDE", 1);
     $smarty -> assign("T_PERSONAL_CTG", 1);
    }
   } catch (Exception $e) {
    $smarty -> assign("T_EXCEPTION_TRACE", $e -> getTraceAsString());
    $message = $e -> getMessage().' ('.$e -> getCode().') &nbsp;<a href = "javascript:void(0)" onclick = "eF_js_showDivPopup(\''._ERRORDETAILS.'\', 2, \'error_details\')">'._MOREINFO.'</a>';
   }
  }
 }
 if ($personal_profile_form) {
  if (isset($_SESSION['facebook_user']) && $_SESSION['facebook_user']) {
   $smarty -> assign("T_USER_STATUS", $_SESSION['facebook_details']['status']['message']);
  } else {
   $smarty -> assign("T_USER_STATUS", $editedUser -> user['status']);
  }
 }
 $renderer = new HTML_QuickForm_Renderer_ArraySmarty($smarty);
 $form -> accept($renderer);
 $smarty -> assign('T_AVATAR_FORM', $renderer -> toArray());
 //End of set the avatar
 /*** Ajax Methods - Add/remove skills/jobs***/
 if (isset($_GET['postAjaxRequest'])) {
  try {
   //echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">';
   /** Post skill - Ajax skill **/
   if (isset($_GET['add_skill'])) {
    if ($_GET['insert'] == "true") {
     $editedEmployee -> addSkills($_GET['add_skill'], $_GET['specification'], $_GET['score']);
    } else if ($_GET['insert'] == "false") {
     $editedEmployee -> removeSkills($_GET['add_skill']);
    } else if (isset($_GET['addAll'])) {
     $skills = $editedEmployee -> getSkills();
     $skills = array_keys($skills);
     $allSkills = EfrontSkill::getAllSkills();
     isset($_GET['filter']) ? $allSkills = eF_filterData($allSkills, $_GET['filter']) : null;
     foreach ($allSkills as $skill) {
      if (!in_array($skill['skill_ID'], $skills)) {
       $editedEmployee -> addSkills($skill['skill_ID'], "");
      }
     }
    } else if (isset($_GET['removeAll'])) {
     $skills = $editedEmployee -> getSkills();
     $skills = array_keys($skills);
     $allSkills = EfrontSkill::getAllSkills();
     isset($_GET['filter']) ? $allSkills = eF_filterData($allSkills, $_GET['filter']) : null;
     foreach ($allSkills as $skill) {
      if (in_array($skill['skill_ID'], $skills)) {
       $editedEmployee -> removeSkills($skill['skill_ID']);
      }
     }
    } else if (isset($_GET['from_skillgap_test'])) {
     $skillsToAdd = array();
     foreach ($_GET as $getkey => $getvalue) {
      if (strpos($getkey,"skill") === 0) {
       $skillId = substr($getkey,5);
       if ($_GET['succeed'.$skillId]) {
        $skillsToAdd[$skillId] = _SUCCEEDEDINASKILLGAPTESTLCWITHASCORE . " $getvalue";
       } else {
        $skillsToAdd[$skillId] = _FAILEDINASKILLGAPTESTLCWITHASCORE . " $getvalue";
       }
      }
     }
     foreach ($skillsToAdd as $skillId => $skillDescription) {
      // The last arguement is set to append and not replace existing skill descriptions
      $editedEmployee -> addSkills($skillId, $skillDescription, $skillScore, true);
     }
    }
    exit;
   } else if (isset($_GET['add_group'])) {
    if ($_GET['insert'] == "true") {
     $editedUser -> addGroups($_GET['add_group']);
    } else if ($_GET['insert'] == "false") {
     $editedUser -> removeGroups($_GET['add_group']);
    } else if (isset($_GET['addAll'])) {
     $groups = eF_getTableDataFlat("groups", "id", "active=1");
     isset($_GET['filter']) ? $groups = eF_filterData($groups, $_GET['filter']) : null;
     $editedUser -> addGroups($groups['id']);
    } else if (isset($_GET['removeAll'])) {
     $groups = eF_getTableDataFlat("groups", "id", "active=1");
     isset($_GET['filter']) ? $groups = eF_filterData($groups, $_GET['filter']) : null;
     $editedUser -> removeGroups($groups['id']);
    }
    exit;
   } else if (isset($_GET['setStatus'])) {
    $editedUser -> setStatus($_GET['setStatus']);
    exit;
   }
  } catch (Exception $e) {
   handleAjaxExceptions($e);
  }
 }
 /** Get the skill list by ajax **/
 $edit_user= $_GET['edit_user'];
 // Create ajax enabled table for employees
 if (isset($_GET['ajax']) && $_GET['ajax'] == 'skillsTable') {
  isset($_GET['limit']) && eF_checkParameter($_GET['limit'], 'uint') ? $limit = $_GET['limit'] : $limit = G_DEFAULT_TABLE_SIZE;
  if (isset($_GET['sort']) && eF_checkParameter($_GET['sort'], 'text')) {
   $sort = $_GET['sort'];
   isset($_GET['order']) && $_GET['order'] == 'desc' ? $order = 'desc' : $order = 'asc';
  } else {
   $sort = 'login';
  }
  // ** Get skills **
  // We do not use the getSkills() method, because it will only return the skills of the employee and we need to present them ALL
  //$skill_categories = eF_getTableData("module_hcd_skill_categories", "*", "", "description","");
  $skills = eF_getTableData("module_hcd_skills LEFT OUTER JOIN module_hcd_skill_categories ON module_hcd_skill_categories.id = module_hcd_skills.categories_ID LEFT OUTER JOIN module_hcd_employee_has_skill ON (module_hcd_employee_has_skill.skill_ID = module_hcd_skills.skill_ID AND module_hcd_employee_has_skill.users_login='$edit_user') LEFT JOIN users ON module_hcd_employee_has_skill.author_login = users.login", "users_login, module_hcd_skills.description, module_hcd_skill_categories.description as category, specification, score, module_hcd_skills.skill_ID, categories_ID, users.surname, users.name","");
  $skills = eF_multiSort($skills, $sort, $order);
  $smarty -> assign("T_SKILLS_SIZE", sizeof($skills));
  if (isset($_GET['filter'])) {
   $skills = eF_filterData($skills, $_GET['filter']);
  }
  if (isset($_GET['limit']) && eF_checkParameter($_GET['limit'], 'int')) {
   isset($_GET['offset']) && eF_checkParameter($_GET['offset'], 'int') ? $offset = $_GET['offset'] : $offset = 0;
   $skills = array_slice($skills, $offset, $limit);
  }
  if (!empty($skills)) {
   $smarty -> assign("T_SKILLS", $skills);
  }
  $smarty -> display($_SESSION['s_type'].'.tpl');
  exit;
 }
 /** Get the employees history by ajax **/
 if (isset($_GET['ajax']) && $_GET['ajax'] == 'historyFormTable') {
  isset($_GET['limit']) && eF_checkParameter($_GET['limit'], 'uint') ? $limit = $_GET['limit'] : $limit = G_DEFAULT_TABLE_SIZE;
  if (isset($_GET['sort']) && eF_checkParameter($_GET['sort'], 'text')) {
   $sort = $_GET['sort'];
   isset($_GET['order']) && $_GET['order'] == 'asc' ? $order = 'asc' : $order = 'desc';
  } else {
   $sort = 'timestamp';
  }
  // Initialize
  $history = array();
  // Get history from events table - 3.6 and on
  // type > 300 is the HCD events
  $history_from_events = eF_getTableData("events", "*", "users_LOGIN = '".$_GET['edit_user']."' AND type > 300");
  $allModules = eF_loadAllModules();
  foreach ($history_from_events as $key => $event) {
   $eventObject = new EfrontEvent($event);
   $history[$key]['event_ID'] = "_" . $event['id'];
   $history[$key]['timestamp'] = $event['timestamp'];
   $history[$key]['message'] = $eventObject ->createMessage($allModules);
  }
  // Get history from module_hcd_events table - for before 3.6
  $history_hcd_events = eF_getTableData("module_hcd_events", "*", "users_login = '".$_GET['edit_user']."' AND event_code <10");
  foreach ($history_hcd_events as $key => $event) {
   $history['_' . $key]['event_ID'] = $event['event_ID'];
   $history['_' . $key]['timestamp'] = $event['timestamp'];
   $history['_' . $key]['message'] = $event['specification'];
  }
  $history = eF_multiSort($history, $sort, $order);
  if (isset($_GET['filter'])) {
   $history = eF_filterData($history , $_GET['filter']);
  }
  $smarty -> assign('T_HISTORY_SIZE', sizeof($history));
  if (isset($_GET['limit']) && eF_checkParameter($_GET['limit'], 'int')) {
   isset($_GET['offset']) && eF_checkParameter($_GET['offset'], 'int') ? $offset = $_GET['offset'] : $offset = 0;
   $history = array_slice($history, $offset, $limit);
  }
  if(!empty($history)) {
   $smarty -> assign("T_HISTORY", $history);
  }
  $smarty -> display($_SESSION['s_type'].'.tpl');
  exit;
 }
 /** Calculate and display course users ajax lists*/
 $courseUser = $editedUser;
 if ($ctg != 'personal' || $currentUser -> user['user_type'] == 'administrator') {
  $showUnassigned = true;
 } else {
  $showUnassigned = false;
 }
 require_once("includes/personal/user_courses.php");
 if (isset($_GET['ajax']) && $_GET['ajax'] == 'toggle_user') {
  $response = array('status' => 1);
  try {
   if ($_GET['type'] == 'course') {
    $editCourse = new EfrontCourse($_GET['id']);
    if ($editCourse -> isUserActiveInCourse($editedUser)) {
     $editCourse -> unConfirm($editedUser);
     $response['access'] = 0;
    } else {
     $editCourse -> confirm($editedUser);
     $response['access'] = 1;
    }
   } else {
    $editLesson = new EfrontLesson($_GET['id']);
    if ($editLesson -> isUserActiveInLesson($editedUser)) {
     $editLesson -> unConfirm($editedUser);
     $response['access'] = 0;
    } else {
     $editLesson -> confirm($editedUser);
     $response['access'] = 1;
    }
   }
   echo json_encode($response);
  } catch (Exception $e) {
   handleAjaxExceptions($e);
  }
  exit;
 }
 /****************************************************************************************************************************************************/
 /*********************************************************** Create the add/edit user form ******************************************************************/
 /****************************************************************************************************************************************************/
 if (isset($_GET['add_user'])) { //We add a new user, so we need to display login field. Only an administrator has the ability to add a user.
  $form = new HTML_QuickForm("add_users_form", "post", basename($_SERVER['PHP_SELF'])."?ctg=users&add_user=1", "", null, true);
  $form -> registerRule('checkParameter', 'callback', 'eF_checkParameter'); //Register this rule for checking user input with our function, eF_checkParameter
  $form -> addElement('text', 'new_login', _LOGIN, 'class = "inputText"');
  $form -> addRule('new_login', _THEFIELD.' '._LOGIN.' '._ISMANDATORY, 'required', null, 'client');
  $form -> addRule('new_login', _INVALIDFIELDDATA, 'checkParameter', 'login');
  $form -> registerRule('checkNotExist', 'callback', 'eF_checkNotExist');
  $form -> addRule('new_login', _THELOGIN.' &quot;'.($form -> exportValue('new_login')).'&quot; '._ALREADYEXISTS, 'checkNotExist', 'login');
  $form -> addElement('password', 'password_', _PASSWORD, 'autocomplete="off" class = "inputText"');
  $form -> addRule('password_', _THEFIELD.' '._PASSWORD.' '._ISMANDATORY, 'required', null, 'client');
  $form -> addRule('password_', str_replace("%x", $GLOBALS['configuration']['password_length'], _PASSWORDMUSTBE6CHARACTERS), 'minlength', $GLOBALS['configuration']['password_length'], 'client');
  $form -> addElement('password', 'passrepeat', _REPEATPASSWORD, 'class = "inputText "');
  $form -> addRule('passrepeat', _THEFIELD.' '._REPEATPASSWORD.' '._ISMANDATORY, 'required', null, 'client');
  $form -> addRule(array('password_', 'passrepeat'), _PASSWORDSDONOTMATCH, 'compare', null, 'client');
 } elseif (isset($_GET['edit_user']) && eF_checkParameter($_GET['edit_user'], 'login')) {
   // In classic eFront, only the administrator may change someone else's data
   ($currentUser -> getType() == "administrator") ? $post_target = "?ctg=users&edit_user=".$_GET['edit_user'] : $post_target = "?ctg=personal&op=account";
  $form = new HTML_QuickForm("change_users_form", "post", basename($_SERVER['PHP_SELF']).$post_target, "", null, true);
  $form -> registerRule('checkParameter', 'callback', 'eF_checkParameter'); //Register this rule for checking user input with our function, eF_checkParameter
  if (!$editedUser -> isLdapUser) { //needs to check ldap
   $form -> addElement('password', 'password_', _PASSWORDLEAVEBLANK, 'autocomplete="off" class = "inputText"');
   $form -> addElement('password', 'passrepeat', _REPEATPASSWORD, 'class = "inputText "');
   $form -> addRule(array('password_', 'passrepeat'), _PASSWORDSDONOTMATCH, 'compare', null, 'client');
  } else {
   $smarty -> assign("T_LDAP_USER", true);
  }
  $smarty -> assign("T_USER_TYPE", $editedUser -> user['user_type']);
  $smarty -> assign("T_REGISTRATION_DATE", $editedUser -> user['timestamp']);
  try {
   $avatar = new EfrontFile($editedUser -> user['avatar']);
   $smarty -> assign ("T_AVATAR", urlencode($editedUser -> user['avatar']));
   //echo $editedUser -> user['avatar']."<BR>";
   //pr($avatar);
   // Get current dimensions
   list($width, $height) = getimagesize($avatar['path']);
   if ($width > 200 || $height > 100) {
    // Get normalized dimensions
    list($newwidth, $newheight) = eF_getNormalizedDims($avatar['path'], 200, 100);
    // The template will check if they are defined and normalize the picture only if needed
    $smarty -> assign("T_NEWWIDTH", $newwidth);
    $smarty -> assign("T_NEWHEIGHT", $newheight);
   }
  } catch (Exception $e) {
   $smarty -> assign ("T_AVATAR", urlencode(G_SYSTEMAVATARSPATH."unknown_small.png"));
  }
 }
 $form -> addElement('text', 'name', _NAME, 'class = "inputText"');
 $form -> addRule('name', _THEFIELD.' '._NAME.' '._ISMANDATORY, 'required', null, 'client');
 //$form -> addRule('name', _INVALIDFIELDDATA, 'checkParameter', 'text'); //Removed as it is not needed any more and it prevented names as O'Neal
 $form -> addElement('text', 'surname', _SURNAME, 'class = "inputText"');
 $form -> addRule('surname', _THEFIELD.' '._SURNAME.' '._ISMANDATORY, 'required', null, 'client');
 //$form -> addRule('surname', _INVALIDFIELDDATA, 'checkParameter', 'text');
 $form -> addElement('text', 'email', _EMAILADDRESS, 'class = "inputText"');
 // Find all groups available to create the select-group drop down
 if (!isset($groups_table)) {
  $groups_table = eF_getTableData("groups", "id, name", "active=1");
 }
 if (!empty($groups_table)) {
  $groups = array ("" => "");
  foreach ($groups_table as $group) {
   $gID = $group['id'];
   $groups["$gID"] = $group['name'];
  }
  $form -> addElement('select', 'group' , _GROUP, $groups ,'class = "inputText" id="group" name="group"');
 } else {
  $form -> addElement('select', 'group' , _GROUP, array ("" => _NOGROUPSDEFINED) ,'class = "inputText" id="group" name="group" disabled="disabled"');
 }
 // Email address is not mandatory for HCD mode
  $form -> addRule('email', _THEFIELD.' '._EMAILADDRESS.' '._ISMANDATORY, 'required', null, 'client');
  $form -> addRule('email', _INVALIDFIELDDATA, 'checkParameter', 'email');
 if (isset($_GET['edit_user'])) {
  $editedUser -> getGroups();
  $init_group = end($editedUser -> groups);
  $form -> setDefaults(array('group' => $init_group['groups_ID']));
 }
 if (isset($_GET['edit_user'])) {
  $form -> setDefaults($editedUser -> user);
  //If the user's type is other than the basic types, set the corresponding select box to point to this one
  if ($editedUser -> user['user_types_ID']) {
   $form -> setDefaults(array('user_type' => $editedUser -> user['user_types_ID']));
  }
 }
 $resultRole = eF_getTableData("users", "user_types_ID", "login='".$currentUser -> login."'");
 $smarty -> assign("T_CURRENTUSERROLEID", $resultRole[0]['user_types_ID']);
 // In HCD mode supervisors - and not only administrators - may create employees
 if ($currentUser -> getType() == "administrator" || (G_VERSIONTYPE == 'enterprise' && $ctg != "personal")) {
  $rolesTypes = EfrontUser :: getRoles();
  if ($resultRole[0]['user_types_ID'] == 0 || $rolesTypes[$resultRole[0]['user_types_ID']] == "administrator") {
   $roles = eF_getTableDataFlat("user_types", "*");
   $roles_array['student'] = _STUDENT;
   $roles_array['professor'] = _PROFESSOR;
   // Only the administrator may assign administrator rights
   // Removed because it unassigns administrators from sub-admins. makriria 30/7/2010
 //		if ($currentUser -> getType() == "administrator" && $resultRole[0]['user_types_ID'] == 0) {
    $roles_array['administrator'] = _ADMINISTRATOR;
 //		}
   if (sizeof($roles) > 0) {
    for ($k = 0; $k < sizeof($roles['id']); $k++) {
     if ($roles['active'][$k] == 1 || (isset($editedUser) && $editedUser -> user['user_types_ID'] == $roles['id'][$k])) { //Make sure that the user's current role will be listed, even if it's deactivated
      $roles_array[$roles['id'][$k]] = $roles['name'][$k];
     }
    }
   }
   $form -> addElement('select', 'user_type', _USERTYPE, $roles_array);
  }
        $form -> addElement('advcheckbox', 'active', _ACTIVEUSER, null, 'class = "inputCheckbox" id="activeCheckbox" ', array(0, 1));
        // Set default values for new users
        if (isset($_GET['add_user'])) {
            $form -> setDefaults(array('active' => '1'));
        }
    }
 if ($GLOBALS['configuration']['onelanguage']) {
  $form -> addElement('hidden', 'languages_NAME', $GLOBALS['configuration']['default_language']);
 } else {
  $form -> addElement('select', 'languages_NAME', _LANGUAGE, EfrontSystem :: getLanguages(true, true));
  // Set default values for new users
  if (isset($_GET['add_user'])) {
   $form -> setDefaults(array('languages_NAME' => $GLOBALS['configuration']['default_language']));
  }
 }
 $timezones = eF_getTimezones();
 $form -> addElement("select", "timezone", _TIMEZONE, $timezones, 'class = "inputText" style="width:20em"');
 // Set default values for new users
 if (isset($_GET['add_user']) || (isset($_GET['edit_user']) && $editedUser -> user['timezone'] == "")) {
  $form -> setDefaults(array('timezone' => $GLOBALS['configuration']['time_zone']));
 }
 if ($_GET['edit_user'] == $_SESSION['s_login']) { //prevent a logged admin to change its type
  $form -> freeze(array('user_type'));
 }
 /****************************************************************************************************************************************************/
 /*********************************************************** Submit posted form: personal information ******************************************************************/
 /****************************************************************************************************************************************************/
 if ((isset($currentUser -> coreAccess['users']) && $currentUser -> coreAccess['users'] != 'change') || (isset($currentUser -> coreAccess['dashboard']) && $currentUser -> coreAccess['dashboard'] != 'change')) {
  $form -> freeze();
 } elseif ($editedUser -> user['user_type'] == 'administrator' && $editedUser -> user['user_types_ID'] == 0 && $currentUser -> user['user_type'] == 'administrator' && $currentUser -> user['user_types_ID'] != 0) {
  $form -> freeze();
 } else {
  $form -> addElement('submit', 'submit_personal_details', _SUBMIT, 'class = "flatButton"');
  if ($form -> isSubmitted() && $form -> validate()) {
   $values = $form -> exportValues();
    $user_profile = eF_getTableData("user_profile", "*", "active=1"); //Get admin-defined form fields for user registration
   //Check the user_type. If it's an id, it means that it's not one of the basic user types; so derive the basic user type and populate the user_types_ID field
   if (is_numeric($values['user_type'])) {
    $result = eF_getTableData("user_types", "id, basic_user_type", "id=".$values['user_type']);
    if (sizeof($result) > 0) {
     $values['user_type'] = $result[0]['basic_user_type'];
     $values['user_types_ID'] = $result[0]['id'];
    } else {
     $values['user_type'] = 'student';
    }
   } else {
    $values['user_types_ID'] = 0;
   }
   /****************************/
   /*** ON ADDING A NEW USER ***/
   /****************************/
   if (isset($_GET['add_user'])) {
    $insertionTimestamp = time(); // needed for the rest of the code to now when the insertion took place
    // Create array from normal user data
    $users_content = array('login' => $values['new_login'],
                                       'name' => $values['name'],
                                       'surname' => $values['surname'],
                                       'active' => $values['active'],
                                       'email' => $values['email'],
                                       'password' => $values['password_'],
                                       'user_type' => $values['user_type'],
                                       'languages_NAME' => $values['languages_NAME'],
                                       'timezone' => $values['timezone'],
                        'timestamp' => $insertionTimestamp,
                                       'user_types_ID' => $values['user_types_ID']);
    foreach ($user_profile as $field) { //Get the custom fields values
     if ($field['type'] == "date") {
      if ($_POST[$field['name'] . '_Month'] != "" && $_POST[$field['name'] . '_Day'] != "" && $_POST[$field['name'] . '_Year'] != "") {
       $users_content[$field['name']] = mktime(0, 0, 0, $_POST[$field['name'] . '_Month'], $_POST[$field['name'] . '_Day'], $_POST[$field['name'] . '_Year']);
      }
     } else {
      $users_content[$field['name']] = $values[$field['name']];
     }
    }
    // Insert the user into the database
    try {
     EfrontUser :: createUser($users_content);
     // Assignment of user group
     if ($values['group']) {
      $group = new EfrontGroup($values['group']);
      $group -> addUsers($values['new_login']);
     }
      eF_redirect("".basename($_SERVER['PHP_SELF'])."?ctg=users&edit_user=".$values['new_login']."&tab=lessons&message=".urlencode(_USERCREATED)."&message_type=success");
     exit;
    } catch (Exception $e) {
     $smarty -> assign("T_EXCEPTION_TRACE", $e -> getTraceAsString());
     $message = $e -> getMessage().' ('.$e -> getCode().') &nbsp;<a href = "javascript:void(0)" onclick = "eF_js_showDivPopup(\''._ERRORDETAILS.'\', 2, \'error_details\')">'._MOREINFO.'</a>';
     $message_type = 'failure';
    }
    /***********************************/
    /*** ON EDITING AN EXISTING USER ***/
    /***********************************/
   } elseif (isset($_GET['edit_user'])) {
    $users_content = array('name' => $values['name'],
                                       'surname' => $values['surname'],
                                       'email' => $values['email'],
                                       'user_types_ID' => $values['user_types_ID'],
                                       'languages_NAME' => $values['languages_NAME'],
                                       'timezone' => $values['timezone']);
    if ($currentUser -> getType() == "administrator") {
     $users_content['active'] = $values['active'];
     //$users_content['languages_NAME'] = $values['languages_NAME'];
     $users_content['user_type'] = $values['user_type'];
     $users_content['pending'] = 0; //The user cannot be pending, since the admin sent this information
    }
    foreach ($user_profile as $field) { //Get the custom fields values
     if ($field['type'] == "date") {
      if ($_POST[$field['name'] . '_Month'] != "" && $_POST[$field['name'] . '_Day'] != "" && $_POST[$field['name'] . '_Year'] != "") {
       $users_content[$field['name']] = mktime(0, 0, 0, $_POST[$field['name'] . '_Month'], $_POST[$field['name'] . '_Day'], $_POST[$field['name'] . '_Year']);
      }
     } else {
      $users_content[$field['name']] = $values[$field['name']];
     }
    }
    if (isset($values['password_']) && $values['password_']) {
     $users_content['password'] = EfrontUser::createPassword($values['password_']);
    }
    // If name/surname changed then the sideframe must be reloaded
    if ($editedUser -> login == $currentUser -> login && ($editedUser -> user['languages_NAME'] != $values['languages_NAME'] || $editedUser -> user['name'] != $values['name'] || $editedUser -> user['surname'] != $values['surname'])) {
     $smarty -> assign("T_REFRESH_SIDE", 1);
     $smarty -> assign("T_PERSONAL_CTG", 1);
     if ($_SESSION['s_language'] != $values['languages_NAME']) {
      $_SESSION['s_language'] = $values['languages_NAME'];
     }
    }
    //eF_updateTableData("users", array_merge($editedUser->user, $users_content), "login='".$_GET['edit_user']."'");
    $editedUser->user = array_merge($editedUser->user, $users_content);
    $editedUser->persist();
    // mpaltas temporary solution: manual OO to keep $editedUser object cache consistent
    if ($editedUser -> user['user_type'] != $values['user_type']) {
     // the new instance will be of the updated type
     $editedUser = EfrontUserFactory :: factory($_GET['edit_user']);
    }
    foreach ($users_content as $field => $content) {
     $editedUser -> user[$field] = $content;
    }
    // end of mpaltas temp solution
    $currentUser -> getType() == "administrator" ? $message = _PERSONALDATACHANGESUCCESSADMIN : $message = _PERSONALDATACHANGESUCCESS;
    $message_type = 'success';
    if (isset($values['password_']) && $values['password_'] && $currentUser -> login == $_GET['edit_user']) { //In case the user changed his password, change it in the session as well
     $_SESSION['s_password'] = $users_content['password'];
    }
    // Assignment of user group
    if ($values['group'] != $init_group['groups_ID']) {
     if ($init_group['groups_ID']) {
      $editedUser -> removeGroups($init_group['groups_ID']);
     }
     if ($values['group']) {
      $editedUser -> addGroups($values['group']);
     } else {
      $groups = eF_getTableDataFlat("groups","id","");
      $editedUser -> removeGroups($groups['id']);
     }
    }
   }
  }
 }
 $renderer = new HTML_QuickForm_Renderer_ArraySmarty($smarty);
 $renderer -> setRequiredTemplate(
       '{$html}{if $required}
            &nbsp;<span class = "formRequired">*</span>
        {/if}');
 $form -> setJsWarnings(_BEFOREJAVASCRIPTERROR, _AFTERJAVASCRIPTERROR);
 $form -> setRequiredNote(_REQUIREDNOTE);
 $form -> accept($renderer);
 $smarty -> assign('T_PERSONAL_DATA_FORM', $renderer -> toArray());
 // Put in the end to include possible updated values
 if ($init_job['branch_ID']) {
  $smarty -> assign("T_BRANCH_INFO", "href=\"" . $currentUser -> getType(). ".php?ctg=module_hcd&op=branches&edit_branch=" . $my_branch_id . "\"");
  $smarty -> assign('my_jobs_label', _JOBDESCRIPTION);
  $smarty -> assign('my_jobs_html', 1 );
 }
 if ($_GET['ctg'] == 'personal' || ($_SESSION['s_type'] == 'administrator' && $currentUser -> user['login'] == $editedUser -> user['login'])) {
  $loadScripts[] = 'scriptaculous/effects';
  unserialize($editedUser -> user['additional_accounts']) ? $additionalAccounts = unserialize($editedUser -> user['additional_accounts']) : $additionalAccounts = array();;
  $smarty -> assign("T_ADDITIONAL_ACCOUNTS", $additionalAccounts);
  if (isset($_GET['ajax']) && $_GET['ajax'] == 'additional_accounts') {
   try {
    if (isset($_GET['fb_login'])) {
    } else {
     if (isset($_GET['delete'])) {
      unset($additionalAccounts[array_search($_GET['login'], $additionalAccounts)]);
     } else {
      if ($_GET['login'] == $_SESSION['s_login']){
       throw new Exception(_CANNOTMAPSAMEACCOUNT);
      }
      if (array_search($_GET['login'], $additionalAccounts)) {
       throw new Exception(_ADDITIONALACCOUNTALREADYEXISTS);
      }
      $newAccount = EfrontUserFactory::factory($_GET['login'], EfrontUser::createPassword($_GET['pwd']));
      $additionalAccounts[] = $newAccount -> user['login'];
      unserialize($newAccount -> user['additional_accounts']) ? $additionalAccounts2 = unserialize($newAccount -> user['additional_accounts']) : $additionalAccounts2 = array();
      $additionalAccounts2[] = $editedUser -> user['login'];
      $newAccount -> user['additional_accounts'] = serialize(array_unique($additionalAccounts2));
      $newAccount -> persist();
     }
     $editedUser -> user['additional_accounts'] = serialize(array_unique($additionalAccounts));
     $editedUser -> persist();
    }
   } catch (Exception $e) {
    handleAjaxExceptions($e);
   }
   exit;
  }
 }
 /****************************************************************************************************************************************************/
 /***************************** [HCD] Retrieve all Employee information to appear on the form: job descriptions, skills, evaluations *****************/
 /****************************************************************************************************************************************************/
 /** GET DATA FOR EMPLOYEE'S PLACEMENTS AND SKILLS **/
 if (isset($editedUser)) {
  $edit_user= $editedUser -> login;//$_GET['edit_user'];
  //$smarty -> assign('T_USERNAME',"" . $editedUser -> user['name'] . " " . $editedUser -> user['surname'] . "");
  //$smarty -> assign('T_SIMPLEUSERNAME',$editedUser -> user['name'] . " " . $editedUser -> user['surname']);
  $smarty -> assign('T_USER', $editedUser -> user);
  /****************************************************************************************************************************************************/
  /***************************** Retrieve all User information to appear on the form: personal information, lessons, courses, certificates, groups ******************/
  /****************************************************************************************************************************************************/
  /** Get certificates **/
  $certificates = $editedUser->getIssuedCertificates();
  //pr($certificates);
  if (!empty($certificates)) {
   $smarty -> assign("T_USER_TO_CERTIFICATES", $certificates);
  }
  try {
   if (isset($_GET['ajax']) && $_GET['ajax'] == "groupsTable") {
    /** Get groups **/
    $groups = eF_getTableData("groups", "*", "active=1");
    $user_groups = $editedUser -> getGroups();
    for ($k = 0; $k < sizeof($groups); $k++) {
     $groups[$k]['partof'] = 0;
     if (in_array($groups[$k]['id'], array_keys($user_groups))) {
      $groups[$k]['partof'] = 1;
     } else if (!$groups[$k]['active'] || $currentUser -> getType() != "administrator") {
      unset($groups[$k]);
     }
    }
    $dataSource = $groups;
    $tableName = 'groupsTable';
    include("sorted_table.php");
   }
  } catch (Exception $e) {
   handleAjaxExceptions($e);
  }
 }
 $smarty -> assign("T_STATISTICS_LINK", array(array('text' => _REPORTS, 'image' => "16x16/reports.png", 'href' => basename($_SERVER['PHP_SELF'])."?ctg=statistics&option=user&sel_user=".$editedUser -> user['login'])));
}
