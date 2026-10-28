<?php
// Local developer fixture only. Copied into the Moodle container by scripts/app.sh.
// This file is part of Moodle - https://moodle.org/ (GNU GPL v3 or later).

define('CLI_SCRIPT', true);
if (getenv('ACCOUNTING_APP_LOCAL_SETUP') !== '1') {
    throw new RuntimeException('Run this through dev/scripts/app.sh in the local Docker stack.');
}
require('/var/www/html/config.php');
if (parse_url($CFG->wwwroot, PHP_URL_HOST) !== 'localhost') {
    throw new RuntimeException('Refusing to provision fixtures on a non-local Moodle site.');
}
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/user/lib.php');

\core\session\manager::set_user(get_admin());
$mobile = new admin_setting_enablemobileservice('enablemobilewebservice', '', '', 0);
$error = $mobile->write_setting('1');
if ($error) {
    throw new RuntimeException($error);
}

$generator = new testing_data_generator();
$marker = 'qtype-accounting-app-fixture';
$course = $DB->get_record('course', ['shortname' => 'ACCOUNTING-APP-TEST']);
if (!$course) {
    $course = $generator->create_course([
        'shortname' => 'ACCOUNTING-APP-TEST',
        'fullname' => 'Accounting App Testing',
        'idnumber' => $marker,
        'summary' => 'Local app tests: debit Cash 500 and Bank 500; credit Sales 1000.',
        'format' => 'topics',
        'numsections' => 1,
    ]);
}
if ($course->idnumber !== $marker) {
    throw new RuntimeException('A course with the fixture shortname already exists and is not owned by this setup.');
}
foreach (['appstudent'] as $username) {
    $student = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id]);
    if (!$student) {
        $studentid = user_create_user((object)[
            'username' => $username, 'password' => 'AppTest123!', 'auth' => 'manual',
            'firstname' => 'App', 'lastname' => $username === 'appstudent' ? 'Student' : 'Smoke',
            'email' => $username . '@example.invalid',
            'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id, 'idnumber' => $marker, 'lang' => 'en',
        ]);
        $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);
    }
    if ($student->idnumber !== $marker) {
        throw new RuntimeException('The fixture username already belongs to another user: ' . $username);
    }
    $generator->enrol_user($student->id, $course->id, 'student');
}

$context = context_course::instance($course->id);
$chart = $DB->get_record('qtype_accounting_charts', ['contextid' => $context->id, 'name' => 'App testing accounts']);
$chartid = $chart ? $chart->id : \qtype_accounting\chart_manager::create_chart('App testing accounts', $context->id);
$accounts = [];
foreach (['1000 Cash', '1200 Bank', '8400 Sales'] as $index => $name) {
    $record = $DB->get_record('qtype_accounting_accounts', ['chartid' => $chartid, 'accountname' => $name]);
    $accounts[] = $record ? $record->id : \qtype_accounting\account_manager::add($chartid, $name, $index);
}
$category = $DB->get_record('question_categories', ['contextid' => $context->id, 'name' => 'App test questions']);
if (!$category) {
    $category = $generator->get_plugin_generator('core_question')->create_question_category([
        'contextid' => $context->id, 'name' => 'App test questions',
    ]);
}

foreach (['de', 'us'] as $format) {
    $name = 'Accounting App ' . strtoupper($format);
    $quiz = $DB->get_record('quiz', ['course' => $course->id, 'name' => $name]);
    if (!$quiz) {
        $quiz = $generator->create_module('quiz', [
            'course' => $course->id, 'name' => $name, 'preferredbehaviour' => 'immediatefeedback',
            'intro' => 'Test Add/Delete, Check, resume, Submit and review. Unlimited attempts.',
            'introformat' => FORMAT_HTML, 'attempts' => 0,
        ]);
    }
    if (!$DB->record_exists('quiz_slots', ['quizid' => $quiz->id])) {
        $form = (object)[
            'name' => 'Split sale ' . strtoupper($format),
            'category' => $category->id . ',' . $context->id, 'context' => $context,
            'questiontext' => ['text' => '<p>Record a sale of 1000: 500 received in Cash and 500 in Bank.</p>' .
                '<p>Debit: 1000 Cash = 500 and 1200 Bank = 500. Credit: 8400 Sales = 1000.</p>', 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => 'Cash 500 + Bank 500 on debit; Sales 1000 on credit.', 'format' => FORMAT_HTML],
            'defaultmark' => 4, 'penalty' => 0, 'status' => 'ready',
            'chartofaccountsid' => $chartid, 'numberformat' => $format, 'accountsindropdown' => 0,
            'debitaccount' => [$accounts[0], $accounts[1]], 'debitamount' => [500, 500],
            'creditaccount' => [$accounts[2], 0], 'creditamount' => [1000, 0],
            'weight_debitaccount' => [1, 1], 'weight_debitamount' => [1, 1],
            'weight_creditaccount' => [1, 1], 'weight_creditamount' => [1, 1],
        ];
        $question = question_bank::get_qtype('accounting')->save_question((object)['qtype' => 'accounting'], $form);
        quiz_add_quiz_question($question->id, $quiz);
    }
    \mod_quiz\grade_calculator::create(\mod_quiz\quiz_settings::create($quiz->id))->recompute_quiz_sumgrades();
}
purge_all_caches();
echo "Local fixtures ready. Course: Accounting App Testing\n";
echo "Student login: appstudent / AppTest123!\n";
echo "Site: {$CFG->wwwroot}\n";
