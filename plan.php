<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['auth_user']['id'])) {
    header('Location: auth.php?mode=signin');
    exit;
}

require_once __DIR__ . '/config/database.php';

$user = $_SESSION['auth_user'];
$pdo = getDatabaseConnection();

$notice = null;
if (isset($_GET['generated']) && (string) $_GET['generated'] === '1') {
    $notice = 'Roadmap generated successfully.';
}

$error = null;
$exam = null;
$profile = null;
$studyPlan = null;

try {
    $examStmt = $pdo->prepare('SELECT exam_title, exam_date FROM user_exams WHERE user_id = :user_id LIMIT 1');
    $examStmt->execute(['user_id' => $user['id']]);
    $exam = $examStmt->fetch() ?: null;

    $profileStmt = $pdo->prepare('SELECT daily_hours, days_per_week, session_minutes, strengths, weaknesses FROM user_study_profiles WHERE user_id = :user_id LIMIT 1');
    $profileStmt->execute(['user_id' => $user['id']]);
    $profile = $profileStmt->fetch() ?: null;

    $planStmt = $pdo->prepare('SELECT exam_title, exam_date, subjects_json, plan_json, generated_at FROM user_study_plans WHERE user_id = :user_id LIMIT 1');
    $planStmt->execute(['user_id' => $user['id']]);
    $planRow = $planStmt->fetch() ?: null;
    if ($planRow) {
        $studyPlan = $planRow;
        $studyPlan['subjects'] = json_decode((string) $planRow['subjects_json'], true) ?: [];
        $studyPlan['plan'] = json_decode((string) $planRow['plan_json'], true) ?: [];
    }
} catch (Throwable $e) {
    $error = 'Unable to load your plan right now.';
}

$fullName = (string) ($user['full_name'] ?? 'Student');
$initial = strtoupper(substr($fullName, 0, 1));

$hasExam = is_array($exam) && !empty($exam['exam_title']) && !empty($exam['exam_date']);
$examTitle = $hasExam ? (string) $exam['exam_title'] : '';
$examDateRaw = $hasExam ? (string) $exam['exam_date'] : '';
$examDateFormatted = $hasExam ? date('l, d M Y', strtotime($examDateRaw)) : '';
$daysUntilExam = $hasExam ? max(0, (int) ceil((strtotime($examDateRaw) - time()) / 86400)) : null;

$weeklyPlan = is_array($studyPlan['plan']['weekly_plan'] ?? null) ? ($studyPlan['plan']['weekly_plan'] ?? []) : [];
$subjectsAll = is_array($studyPlan['subjects'] ?? null) ? ($studyPlan['subjects'] ?? []) : [];

$subjectsHigh = [];
$subjectsMedium = [];
$subjectsLow = [];
foreach ($subjectsAll as $subject) {
    if (!is_array($subject)) {
        continue;
    }
    $name = (string) ($subject['name'] ?? '');
    if (trim($name) === '') {
        continue;
    }
    $priority = strtolower((string) ($subject['priority'] ?? 'medium'));
    if (!in_array($priority, ['high', 'medium', 'low'], true)) {
        $priority = 'medium';
    }
    if ($priority === 'high') {
        $subjectsHigh[] = $subject;
    } elseif ($priority === 'low') {
        $subjectsLow[] = $subject;
    } else {
        $subjectsMedium[] = $subject;
    }
}

$renderSubjectRow = static function (string $label, array $subjects): void {
    if (empty($subjects)) {
        return;
    }
    ?>
    <div class="planner-subject-group">
        <div class="planner-subject-group-head">
            <strong><?php echo htmlspecialchars($label, ENT_QUOTES); ?></strong>
            <span><?php echo htmlspecialchars((string) count($subjects), ENT_QUOTES); ?></span>
        </div>
        <ul class="planner-subjects planner-subjects--row">
            <?php foreach ($subjects as $subject): ?>
                <?php if (!is_array($subject)) { continue; } ?>
                <?php
                    $name = (string) ($subject['name'] ?? '');
                    $priority = (string) ($subject['priority'] ?? '');
                    $notes = (string) ($subject['notes'] ?? '');
                    if (trim($name) === '') {
                        continue;
                    }
                ?>
                <li class="planner-subject-card">
                    <strong><?php echo htmlspecialchars($name, ENT_QUOTES); ?></strong>
                    <?php if ($priority): ?><span class="planner-tag"><?php echo htmlspecialchars($priority, ENT_QUOTES); ?></span><?php endif; ?>
                    <?php if ($notes): ?><span class="planner-subject-notes"><?php echo htmlspecialchars($notes, ENT_QUOTES); ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyGuru | Your plan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-body">
    <nav class="nav dashboard-nav">
        <div class="logo"><a href="index.php">StudyGuru</a></div>
        <div class="dashboard-user">
            <div class="dashboard-user-meta">
                <span class="dashboard-user-label">Your plan</span>
                <strong><?php echo htmlspecialchars($fullName, ENT_QUOTES); ?></strong>
            </div>
            <div class="dashboard-avatar"><span><?php echo htmlspecialchars($initial, ENT_QUOTES); ?></span></div>
            <a class="solid" href="goal.php">Change your goal</a>
            <a class="ghost" href="dashboard.php">Dashboard</a>
        </div>
    </nav>

    <main class="dashboard-shell">
        <?php if ($notice): ?>
            <div class="auth-alert auth-alert--success"><?php echo htmlspecialchars($notice, ENT_QUOTES); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="auth-alert auth-alert--error"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
        <?php endif; ?>

        <section class="dashboard-card dashboard-card--exam">
            <p class="eyebrow">Step 1 · Exam</p>
            <?php if ($hasExam): ?>
                <h2><?php echo htmlspecialchars($examTitle, ENT_QUOTES); ?></h2>
                <p class="dashboard-note">Target date: <strong><?php echo htmlspecialchars($examDateFormatted, ENT_QUOTES); ?></strong><?php echo $daysUntilExam !== null ? ' · ' . htmlspecialchars((string) $daysUntilExam, ENT_QUOTES) . ' days left' : ''; ?></p>
            <?php else: ?>
                <h2>No exam saved</h2>
                <p class="dashboard-note">Set your goal to unlock the roadmap.</p>
            <?php endif; ?>
            <a class="solid" href="goal.php">Edit exam goal</a>
        </section>

        <section class="dashboard-card">
            <p class="eyebrow">Step 2 · Preferences</p>
            <h2>Study preferences</h2>
            <?php if (is_array($profile)):
                $dailyHours = (string) ($profile['daily_hours'] ?? '');
                $daysPerWeek = (string) ($profile['days_per_week'] ?? '');
                $sessionMinutes = (string) ($profile['session_minutes'] ?? '');
                $strengths = (string) ($profile['strengths'] ?? '');
                $weaknesses = (string) ($profile['weaknesses'] ?? '');
            ?>
                <p class="dashboard-note">
                    <strong><?php echo htmlspecialchars($dailyHours !== '' ? $dailyHours : '2', ENT_QUOTES); ?></strong> hours/day ·
                    <strong><?php echo htmlspecialchars($daysPerWeek !== '' ? $daysPerWeek : '6', ENT_QUOTES); ?></strong> days/week ·
                    <strong><?php echo htmlspecialchars($sessionMinutes !== '' ? $sessionMinutes : '60', ENT_QUOTES); ?></strong> min/session
                </p>
                <?php if (trim($strengths) !== ''): ?>
                    <p class="dashboard-note">Strengths: <?php echo htmlspecialchars($strengths, ENT_QUOTES); ?></p>
                <?php endif; ?>
                <?php if (trim($weaknesses) !== ''): ?>
                    <p class="dashboard-note">Weaknesses: <?php echo htmlspecialchars($weaknesses, ENT_QUOTES); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="dashboard-note">No preferences saved yet. Set your routine for a better roadmap.</p>
            <?php endif; ?>
            <a class="solid" href="goal.php">Edit preferences</a>
        </section>

        <section class="dashboard-card dashboard-card--planner">
            <p class="eyebrow">Step 3 · Roadmap</p>
            <h2>Roadmap & subjects</h2>

            <?php if (!$hasExam): ?>
                <p class="dashboard-note">Save your exam goal first.</p>
                <a class="solid" href="goal.php">Set goal</a>
            <?php elseif (!$studyPlan || empty($weeklyPlan)) : ?>
                <p class="dashboard-note">No roadmap generated yet. Go to the dashboard and generate your roadmap.</p>
                <a class="solid" href="dashboard.php">Go to dashboard</a>
            <?php else: ?>
                <div class="planner-kpis">
                    <div class="planner-kpi"><span class="planner-kpi-label">Weeks</span><strong class="planner-kpi-value"><?php echo htmlspecialchars((string) count($weeklyPlan), ENT_QUOTES); ?></strong></div>
                    <div class="planner-kpi"><span class="planner-kpi-label">Subjects</span><strong class="planner-kpi-value"><?php echo htmlspecialchars((string) count($subjectsAll), ENT_QUOTES); ?></strong></div>
                    <div class="planner-kpi"><span class="planner-kpi-label">High priority</span><strong class="planner-kpi-value"><?php echo htmlspecialchars((string) count($subjectsHigh), ENT_QUOTES); ?></strong></div>
                </div>

                <div class="planner-output">
                    <div class="planner-output-block planner-output-block--roadmap">
                        <div class="planner-output-head">
                            <h3>Roadmap</h3>
                        </div>
                        <div class="planner-weeks">
                            <?php foreach ($weeklyPlan as $week): ?>
                                <?php if (!is_array($week)) { continue; } ?>
                                <?php $weekNo = (int) ($week['week'] ?? 0); ?>
                                <article class="planner-week">
                                    <header>
                                        <strong>Week <?php echo htmlspecialchars((string) ($week['week'] ?? ''), ENT_QUOTES); ?></strong>
                                        <span><?php echo htmlspecialchars((string) ($week['focus'] ?? ''), ENT_QUOTES); ?></span>
                                    </header>
                                    <?php if (isset($week['goals']) && is_array($week['goals'])): ?>
                                        <ul class="planner-goals">
                                            <?php foreach ($week['goals'] as $goal): ?>
                                                <?php if (!is_string($goal) || trim($goal) === '') { continue; } ?>
                                                <li><?php echo htmlspecialchars($goal, ENT_QUOTES); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>

                                    <?php if (isset($week['sessions']) && is_array($week['sessions']) && !empty($week['sessions'])): ?>
                                        <div class="planner-sessions">
                                            <?php foreach ($week['sessions'] as $session): ?>
                                                <?php if (!is_array($session)) { continue; } ?>
                                                <?php $day = (string) ($session['day'] ?? ''); ?>
                                                <?php $blocks = is_array($session['blocks'] ?? null) ? ($session['blocks'] ?? []) : []; ?>
                                                <?php if ($day === '' || empty($blocks)) { continue; } ?>
                                                <div class="planner-day">
                                                    <div class="planner-day-head">
                                                        <strong><?php echo htmlspecialchars($day, ENT_QUOTES); ?></strong>
                                                        <span class="planner-day-actions">
                                                            <span class="planner-day-sub">Blocks: <?php echo htmlspecialchars((string) count($blocks), ENT_QUOTES); ?></span>
                                                            <a class="solid planner-open-btn" href="day.php?week=<?php echo htmlspecialchars((string) $weekNo, ENT_QUOTES); ?>&day=<?php echo rawurlencode($day); ?>">Open</a>
                                                        </span>
                                                    </div>
                                                    <ul class="planner-blocks">
                                                        <?php foreach ($blocks as $block): ?>
                                                            <?php if (!is_array($block)) { continue; } ?>
                                                            <?php
                                                                $bSub = (string) ($block['subject'] ?? '');
                                                                $bTopic = (string) ($block['topic'] ?? '');
                                                                $bTopicDetail = (string) ($block['topic_detail'] ?? '');
                                                                $bMin = (int) ($block['minutes'] ?? 0);
                                                                $bAct = (string) ($block['activity'] ?? '');
                                                                if (trim($bSub) === '' || $bMin <= 0) {
                                                                    continue;
                                                                }
                                                            ?>
                                                            <li class="planner-block">
                                                                <span class="planner-block-title"><?php echo htmlspecialchars($bSub, ENT_QUOTES); ?></span>
                                                                <?php if (trim($bTopic) !== ''): ?>
                                                                    <span class="planner-block-topic"><?php echo htmlspecialchars($bTopic, ENT_QUOTES); ?></span>
                                                                <?php endif; ?>
                                                                <?php if (trim($bTopicDetail) !== ''): ?>
                                                                    <span class="planner-block-detail"><?php echo htmlspecialchars($bTopicDetail, ENT_QUOTES); ?></span>
                                                                <?php endif; ?>
                                                                <span class="planner-block-meta"><?php echo htmlspecialchars((string) $bMin, ENT_QUOTES); ?> min<?php echo $bAct !== '' ? ' · ' . htmlspecialchars($bAct, ENT_QUOTES) : ''; ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="planner-output-block planner-output-block--subjects">
                        <h3>Subjects</h3>
                        <?php $renderSubjectRow('High priority', $subjectsHigh); ?>
                        <?php $renderSubjectRow('Medium priority', $subjectsMedium); ?>
                        <?php $renderSubjectRow('Low priority', $subjectsLow); ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
