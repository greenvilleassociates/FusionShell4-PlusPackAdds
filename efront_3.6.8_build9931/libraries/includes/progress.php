<?php
if (str_replace(DIRECTORY_SEPARATOR, "/", __FILE__) == $_SERVER['SCRIPT_FILENAME']) {
    exit;
}

if (isset($currentUser -> coreAccess['progress']) && $currentUser -> coreAccess['progress'] == 'hidden') {
    eF_redirect(basename($_SERVER['PHP_SELF'])."?ctg=control_panel&message=".urlencode(_UNAUTHORIZEDACCESS)."&message_type=failure");exit;
}

$loadScripts[] = 'includes/progress';

if ($_student_) {
    $currentUser -> coreAccess['progress'] = 'view';
    $_GET['edit_user'] = $currentUser -> user['login'];
 $smarty -> assign("T_STUDENT_ROLE", true);
}

if (isset($_GET['edit_user']) && eF_checkParameter($_GET['edit_user'], 'login')) {
 $editedUser = EfrontUserFactory :: factory($_GET['edit_user']);
 $load_editor = true;
    //$lessonUser  = EfrontUserFactory :: factory($_GET['edit_user']);

    //Check conditions
    $currentContent = new EfrontContentTree($currentLesson);
    $seenContent = EfrontStats :: getStudentsSeenContent($currentLesson -> lesson['id'], $editedUser -> user['login']);
    $conditions = $currentLesson -> getConditions();
    foreach ($iterator = new EfrontVisitableFilterIterator(new EfrontNodeFilterIterator(new RecursiveIteratorIterator(new RecursiveArrayIterator($currentContent -> tree), RecursiveIteratorIterator :: SELF_FIRST))) as $key => $value) {
        $visitableContentIds[$key] = $key; //Get the not-test unit ids for this content
    }
    foreach ($iterator = new EfrontTestsFilterIterator(new EfrontVisitableFilterIterator(new EfrontNodeFilterIterator(new RecursiveIteratorIterator(new RecursiveArrayIterator($currentContent -> tree), RecursiveIteratorIterator :: SELF_FIRST)))) as $key => $value) {
        $testsIds[$key] = $key; //Get the not-test unit ids for this content
    }

    list($conditionsStatus, $lessonPassed) = EfrontStats :: checkConditions($seenContent[$currentLesson -> lesson['id']][$editedUser -> user['login']], $conditions, $visitableContentIds, $testsIds);
    $smarty -> assign("T_CONDITIONS", $conditions);
    $smarty -> assign("T_CONDITIONS_STATUS", $conditionsStatus);
    foreach ($iterator = new EfrontAttributeFilterIterator(new RecursiveIteratorIterator(new RecursiveArrayIterator($currentContent -> tree)), array('id', 'name')) as $key => $value) {
        $key == 'id' ? $ids[] = $value : $names[] = $value;
    }
    $smarty -> assign("T_TREE_NAMES", array_combine($ids, $names));

    $form = new HTML_QuickForm("edit_user_complete_lesson_form", "post", basename($_SERVER['PHP_SELF']).'?ctg=progress&edit_user='.$editedUser -> user['login'], "", null, true);
    $form -> registerRule('checkParameter', 'callback', 'eF_checkParameter'); //Register this rule for checking user input with our function, eF_checkParameter

    $form -> addElement('advcheckbox', 'completed', _COMPLETED, null, 'class = "inputCheckbox"'); //Whether the user has completed the lesson
    $form -> addElement('text', 'score', _SCORE, 'class = "inputText"'); //The user lesson score
    $form -> addRule('score', _THEFIELD.' "'._SCORE.'" '._MUSTBENUMERIC, 'numeric', null, 'client'); //The score must be numeric
    $form -> addRule('score', _RATEMUSTBEBETWEEN0100, 'callback', create_function('$a', 'return ($a >= 0 && $a <= 100);')); //The score must be between 0 and 100
    $form -> addElement('textarea', 'comments', _COMMENTS, 'class = "inputContentTextarea simpleEditor" style = "width:100%;height:5em;"'); //Comments on student's performance

    //$user_data  = eF_getTableData("users_to_lessons", "*", "users_LOGIN='".$editedUser -> user['login']."' and lessons_ID=".$_SESSION['s_lessons_ID']);
//    $userStats  = EfrontStats::getUsersLessonStatus($currentLesson, $editedUser -> user['login']);
//    pr($userStats);
    $userStats = $editedUser -> getUserStatusInLessons($currentLesson);
    $userStats = $userStats[$currentLesson -> lesson['id']] -> lesson;
//    pr($userStats);exit;

    $form -> setDefaults(array("completed" => $userStats['completed'],
                               "score" => $userStats['score'],
                               "comments" => $userStats['comments'] ? $userStats['comments'] : ''));

    if (isset($currentUser -> coreAccess['progress']) && $currentUser -> coreAccess['progress'] != 'change') {
        $form -> freeze();
    } else {
        $form -> addElement('submit', 'submit_lesson_complete', _SUBMIT, 'class = "flatButton"'); //The submit button
        if ($form -> isSubmitted() && $form -> validate()) {
            if ($form -> exportValue('completed')) {
                $lessonUser = EfrontUserFactory :: factory($editedUser -> user['login'], false, 'student');
                $lessonUser -> completeLesson($currentLesson -> lesson['id'], $form -> exportValue('score'), $form -> exportValue('comments'));
            } else {
                eF_updateTableData("users_to_lessons", array('completed' => 0, 'score' => 0, 'to_timestamp' => null), "users_LOGIN = '".$editedUser -> user['login']."' and lessons_ID=".$currentLesson -> lesson['id']);
//		        $cacheKey = "user_lesson_status:lesson:".$currentLesson -> lesson['id']."user:".$editedUser -> user['login'];
//		        Cache::resetCache($cacheKey);
            }

            eF_redirect(basename($_SERVER['PHP_SELF']).'?ctg=progress&message='.urlencode(_STUDENTSTATUSCHANGED).'&message_type=success');
        }
    }

    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($smarty);

    $form -> setJsWarnings(_BEFOREJAVASCRIPTERROR, _AFTERJAVASCRIPTERROR);
    $form -> setRequiredNote(_REQUIREDNOTE);
    $form -> accept($renderer);

    $smarty -> assign('T_COMPLETE_LESSON_FORM', $renderer -> toArray());
    $doneTests = EfrontStats :: getDoneTestsPerUser($_GET['edit_user'], false, $currentLesson -> lesson['id']);

    $result = EfrontStats :: getStudentsDoneTests($currentLesson -> lesson['id'], $_GET['edit_user']);
    foreach ($result[$_GET['edit_user']] as $key => $value) {
        if ($value['scorm']) {
            $scormDoneTests[$key] = $value;
        }
    }

    $testNames = eF_getTableDataFlat("tests t, content c", "t.id, c.name", "c.id=t.content_ID and c.ctg_type='tests' and c.lessons_ID=".$currentLesson -> lesson['id']);
    $testNames = array_combine($testNames['id'], $testNames['name']);


    foreach($doneTests[$_GET['edit_user']] as $key => $value) {
        if (in_array($key, array_keys($testNames))) {
            $lastTest = unserialize($doneTests[$_GET['edit_user']][$value['last_test_id']]);
            $userStats['done_tests'][$key] = array('name' => $testNames[$key], 'score' => $value['average_score'], 'last_test_id' => $value['last_test_id'], 'last_score' => $value['scores'][$value['last_test_id']], 'times_done' => $value['times_done'], 'content_ID' => $value[$value['last_test_id']]['content_ID']);
        }
    }
    foreach($scormDoneTests as $key => $value) {
        $userStats['scorm_done_tests'][$key] = array('name' => $value['name'], 'score' => $value['score'], 'content_ID' => $key);
    }

    $notDoneTests = array_diff(array_keys($testNames), array_keys($doneTests[$_GET['edit_user']]));
    $smarty -> assign("T_PENDING_TESTS", $notDoneTests);

    unset($userStats['done_tests']['average_score']);

    $timeReport = new EfrontTimes();
    $userTime = $timeReport -> getUserSessionTimeInLesson($editedUser -> user['login'], $currentLesson -> lesson['id']);
    $userTime = $timeReport -> formatTimeForReporting($userTime);
    $smarty -> assign("T_USER_LESSONS_INFO", $userStats);

    $smarty -> assign("T_USER_TIME", $userTime);

    $userProjects = EfrontStats :: getStudentsAssignedProjects($currentLesson -> lesson['id'], $editedUser -> user['login']);
    $smarty -> assign("T_USER_PROJECTS", $userProjects[$editedUser -> user['login']]);







 $moduleFieldsets = array();
 foreach ($currentUser -> getModules() as $module) {
  if ($moduleFieldset = $module -> getFieldsetSmartyTpl('lesson_progress')) {
   $moduleFieldsets[] = $moduleFieldset;
  }
 }
 $smarty -> assign("T_MODULE_FIELDSETS", $moduleFieldsets);

}

try {
 if (isset($_GET['ajax']) && isset($_GET['reset_user'])) {
  $user = EfrontUserFactory :: factory($_GET['reset_user']);
  $user -> resetProgressInLesson($currentLesson);
  exit;
 }
 if (isset($_GET['ajax']) && $_GET['ajax'] == 'usersTable') {
  $constraints = createConstraintsFromSortedTable() + array('archive' => false, 'return_objects' => false);
  foreach (EfrontLessonUser :: getLessonsRoles() as $key => $value) {
   $value != 'student' OR $studentRoles[] = $key;
  }
  $constraints['condition'] = "ul.user_type in ('".implode("','", $studentRoles)."')";
  $users = $currentLesson -> getLessonStatusForUsers($constraints);
  $totalEntries = $currentLesson -> countLessonUsers($constraints);
  $dataSource = $users;
  $smarty -> assign("T_TABLE_SIZE", $totalEntries);
 }
 $tableName = $_GET['ajax'];
 $alreadySorted = true;
 include("sorted_table.php");
} catch (Exception $e) {
 handleAjaxExceptions($e);
}
