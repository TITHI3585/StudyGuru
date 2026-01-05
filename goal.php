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

$examOptions = [
    'CAT · MBA Entrance',
    'JEE Main · Engineering',
    'JEE Advanced · Engineering',
    'NEET UG · Medical',
    'GATE · Postgraduate Engineering',
    'UPSC CSE · Civil Services',
    'SSC CGL · Government Services',
    'IBPS / Bank PO · Banking',
    'CLAT · Law Entrance',
    'CUET UG · Central Universities'
];

$examErrors = [];
$profileErrors = [];
$success = null;

$examFormValues = ['exam_title' => '', 'exam_date' => ''];
$profileFormValues = [
    'daily_hours' => '2.0',
    'days_per_week' => '6',
    'session_minutes' => '60',
    'strengths' => '',
    'weaknesses' => ''
];

try {
    $examStmt = $pdo->prepare('SELECT exam_title, exam_date FROM user_exams WHERE user_id = :user_id LIMIT 1');
    $examStmt->execute(['user_id' => $user['id']]);
    $exam = $examStmt->fetch() ?: null;

    if ($exam) {
        $examFormValues = [
            'exam_title' => (string) ($exam['exam_title'] ?? ''),
            'exam_date' => (string) ($exam['exam_date'] ?? ''),
        ];
    }

    $profileStmt = $pdo->prepare('SELECT daily_hours, days_per_week, session_minutes, strengths, weaknesses FROM user_study_profiles WHERE user_id = :user_id LIMIT 1');
    $profileStmt->execute(['user_id' => $user['id']]);
    $profile = $profileStmt->fetch() ?: null;

    if ($profile) {
        $profileFormValues = [
            'daily_hours' => (string) ($profile['daily_hours'] ?? '2.0'),
            'days_per_week' => (string) ($profile['days_per_week'] ?? '6'),
            'session_minutes' => (string) ($profile['session_minutes'] ?? '60'),
            'strengths' => (string) ($profile['strengths'] ?? ''),
            'weaknesses' => (string) ($profile['weaknesses'] ?? ''),
        ];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'exam') {
        $examTitleInput = trim((string) ($_POST['exam_title'] ?? ''));
        $examDateInput = trim((string) ($_POST['exam_date'] ?? ''));
        $examYearInput = trim((string) ($_POST['exam_year'] ?? ''));
        $examMonthInput = trim((string) ($_POST['exam_month'] ?? ''));
        $examDayInput = trim((string) ($_POST['exam_day'] ?? ''));

        if ($examDateInput === '' && ($examYearInput !== '' || $examMonthInput !== '' || $examDayInput !== '')) {
            $year = (int) $examYearInput;
            $month = (int) $examMonthInput;
            $day = (int) $examDayInput;
            if (checkdate($month, $day, $year)) {
                $examDateInput = sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        $examFormValues = ['exam_title' => $examTitleInput, 'exam_date' => $examDateInput];

        if ($examTitleInput === '') {
            $examErrors[] = 'Exam name is required.';
        }

        $dateObject = DateTime::createFromFormat('Y-m-d', $examDateInput) ?: null;
        if (!$dateObject || $dateObject->format('Y-m-d') !== $examDateInput) {
            $examErrors[] = 'Choose a valid exam date.';
        }

        if (empty($examErrors)) {
            $saveExam = $pdo->prepare('INSERT INTO user_exams (user_id, exam_title, exam_date) VALUES (:user_id, :exam_title, :exam_date)
                ON DUPLICATE KEY UPDATE exam_title = VALUES(exam_title), exam_date = VALUES(exam_date), updated_at = CURRENT_TIMESTAMP');
            $saveExam->execute([
                'user_id' => $user['id'],
                'exam_title' => $examTitleInput,
                'exam_date' => $examDateInput,
            ]);
            $success = 'Goal updated.';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'study_profile') {
        $dailyHours = trim((string) ($_POST['daily_hours'] ?? ''));
        $daysPerWeek = trim((string) ($_POST['days_per_week'] ?? ''));
        $sessionMinutes = trim((string) ($_POST['session_minutes'] ?? ''));
        $strengths = trim((string) ($_POST['strengths'] ?? ''));
        $weaknesses = trim((string) ($_POST['weaknesses'] ?? ''));

        $profileFormValues = [
            'daily_hours' => $dailyHours,
            'days_per_week' => $daysPerWeek,
            'session_minutes' => $sessionMinutes,
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
        ];

        $dailyHoursNumber = (float) $dailyHours;
        $daysPerWeekNumber = (int) $daysPerWeek;
        $sessionMinutesNumber = (int) $sessionMinutes;

        if ($dailyHoursNumber < 0.5 || $dailyHoursNumber > 12) {
            $profileErrors[] = 'Daily study hours must be between 0.5 and 12.';
        }
        if ($daysPerWeekNumber < 1 || $daysPerWeekNumber > 7) {
            $profileErrors[] = 'Days per week must be between 1 and 7.';
        }
        if ($sessionMinutesNumber < 20 || $sessionMinutesNumber > 240) {
            $profileErrors[] = 'Session duration must be between 20 and 240 minutes.';
        }

        if (empty($profileErrors)) {
            $saveProfile = $pdo->prepare('INSERT INTO user_study_profiles (user_id, daily_hours, days_per_week, session_minutes, strengths, weaknesses)
                VALUES (:user_id, :daily_hours, :days_per_week, :session_minutes, :strengths, :weaknesses)
                ON DUPLICATE KEY UPDATE daily_hours = VALUES(daily_hours), days_per_week = VALUES(days_per_week), session_minutes = VALUES(session_minutes), strengths = VALUES(strengths), weaknesses = VALUES(weaknesses), updated_at = CURRENT_TIMESTAMP');
            $saveProfile->execute([
                'user_id' => $user['id'],
                'daily_hours' => $dailyHoursNumber,
                'days_per_week' => $daysPerWeekNumber,
                'session_minutes' => $sessionMinutesNumber,
                'strengths' => $strengths ?: null,
                'weaknesses' => $weaknesses ?: null,
            ]);
            $success = 'Preferences updated.';
        }
    }
} catch (Throwable $e) {
    $examErrors[] = 'Database error. Please try again.';
}

$fullName = (string) ($user['full_name'] ?? 'Student');
$initial = strtoupper(substr($fullName, 0, 1));

$currentExamValue = (string) ($examFormValues['exam_title'] ?? '');
$currentExamDateValue = (string) ($examFormValues['exam_date'] ?? '');

$dateYear = '';
$dateMonth = '';
$dateDay = '';
if ($currentExamDateValue !== '') {
    $dateObject = DateTime::createFromFormat('Y-m-d', $currentExamDateValue) ?: null;
    if ($dateObject && $dateObject->format('Y-m-d') === $currentExamDateValue) {
        $dateYear = $dateObject->format('Y');
        $dateMonth = $dateObject->format('m');
        $dateDay = $dateObject->format('d');
    }
}

$yearStart = (int) date('Y');
$yearEnd = $yearStart + 10;
$optionList = $examOptions;
if ($currentExamValue && !in_array($currentExamValue, $optionList, true)) {
    array_unshift($optionList, $currentExamValue);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyGuru | Change goal</title>
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
                <span class="dashboard-user-label">Goal</span>
                <strong><?php echo htmlspecialchars($fullName, ENT_QUOTES); ?></strong>
            </div>
            <div class="dashboard-avatar"><span><?php echo htmlspecialchars($initial, ENT_QUOTES); ?></span></div>
            <a class="ghost" href="plan.php">Back</a>
        </div>
    </nav>

    <main class="dashboard-shell">
        <?php if ($success): ?>
            <div class="auth-alert auth-alert--success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
        <?php endif; ?>

        <section class="dashboard-card dashboard-card--exam">
            <p class="eyebrow">Edit goal</p>
            <h2>Exam goal</h2>

            <?php if ($examErrors): ?>
                <div class="auth-alert auth-alert--error">
                    <ul>
                        <?php foreach ($examErrors as $err): ?>
                            <li><?php echo htmlspecialchars($err, ENT_QUOTES); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="dashboard-form">
                <input type="hidden" name="form" value="exam">
                <label>
                    Exam name
                    <select name="exam_title" required>
                        <option value="">Select your exam</option>
                        <?php foreach ($optionList as $optionLabel): ?>
                            <option value="<?php echo htmlspecialchars($optionLabel, ENT_QUOTES); ?>" <?php echo ($optionLabel === $currentExamValue) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($optionLabel, ENT_QUOTES); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Exam date
                    <div class="dashboard-date-grid">
                        <select name="exam_day" required>
                            <option value="">Day</option>
                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                <?php $v = sprintf('%02d', $d); ?>
                                <option value="<?php echo htmlspecialchars($v, ENT_QUOTES); ?>" <?php echo ($v === $dateDay) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $d, ENT_QUOTES); ?></option>
                            <?php endfor; ?>
                        </select>

                        <select name="exam_month" required>
                            <option value="">Month</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <?php $v = sprintf('%02d', $m); ?>
                                <option value="<?php echo htmlspecialchars($v, ENT_QUOTES); ?>" <?php echo ($v === $dateMonth) ? 'selected' : ''; ?>><?php echo htmlspecialchars(date('M', mktime(0, 0, 0, $m, 1)), ENT_QUOTES); ?></option>
                            <?php endfor; ?>
                        </select>

                        <select name="exam_year" required>
                            <option value="">Year</option>
                            <?php for ($y = $yearStart; $y <= $yearEnd; $y++): ?>
                                <option value="<?php echo htmlspecialchars((string) $y, ENT_QUOTES); ?>" <?php echo ((string) $y === $dateYear) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $y, ENT_QUOTES); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <input type="hidden" name="exam_date" value="<?php echo htmlspecialchars($currentExamDateValue, ENT_QUOTES); ?>">
                </label>

                <button type="submit">Save goal</button>
            </form>
        </section>

        <section class="dashboard-card">
            <p class="eyebrow">Edit preferences</p>
            <h2>Study preferences</h2>

            <?php if ($profileErrors): ?>
                <div class="auth-alert auth-alert--error">
                    <ul>
                        <?php foreach ($profileErrors as $err): ?>
                            <li><?php echo htmlspecialchars($err, ENT_QUOTES); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="dashboard-form dashboard-form--profile">
                <input type="hidden" name="form" value="study_profile">
                <label>
                    Daily study hours
                    <input type="number" step="0.5" min="0.5" max="12" name="daily_hours" value="<?php echo htmlspecialchars($profileFormValues['daily_hours'] ?? '2.0', ENT_QUOTES); ?>" required>
                </label>
                <label>
                    Days per week
                    <input type="number" min="1" max="7" name="days_per_week" value="<?php echo htmlspecialchars($profileFormValues['days_per_week'] ?? '6', ENT_QUOTES); ?>" required>
                </label>
                <label>
                    Session duration (minutes)
                    <input type="number" min="20" max="240" name="session_minutes" value="<?php echo htmlspecialchars($profileFormValues['session_minutes'] ?? '60', ENT_QUOTES); ?>" required>
                </label>
                <label>
                    Strengths (optional)
                    <textarea name="strengths" rows="2" placeholder="e.g., Algebra, RC, Biology diagrams..."><?php echo htmlspecialchars($profileFormValues['strengths'] ?? '', ENT_QUOTES); ?></textarea>
                </label>
                <label>
                    Weaknesses (optional)
                    <textarea name="weaknesses" rows="2" placeholder="e.g., Geometry, DI sets, Organic reactions..."><?php echo htmlspecialchars($profileFormValues['weaknesses'] ?? '', ENT_QUOTES); ?></textarea>
                </label>
                <button type="submit">Save preferences</button>
            </form>

            <p class="dashboard-note" style="margin-top: 0.9rem;">After changing your goal or preferences, generate a fresh roadmap from the dashboard.</p>
            <a class="solid" href="dashboard.php">Generate roadmap</a>
        </section>
    </main>
</body>
</html>
