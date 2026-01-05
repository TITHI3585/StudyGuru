<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['auth_user']['id'])) {
    header('Location: auth.php?mode=signin');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/youtube.php';

function studyguruBuildYouTubeQuery(string $examTitle, string $subject, string $topic): string
{
    $examTitle = trim($examTitle);
    $subject = trim($subject);
    $topic = trim($topic);

    $parts = [];
    if ($examTitle !== '') {
        $parts[] = $examTitle;
    }
    if ($subject !== '') {
        $parts[] = $subject;
    }
    if ($topic !== '') {
        $parts[] = $topic;
    }

    // Keep query short but specific.
    $base = trim(implode(' ', $parts));
    if ($base === '') {
        return '';
    }
    return $base . ' lecture';
}

$user = $_SESSION['auth_user'];
$pdo = getDatabaseConnection();

$weekParam = isset($_GET['week']) ? (int) $_GET['week'] : 0;
$dayParam = isset($_GET['day']) ? trim((string) $_GET['day']) : '';

$error = null;
$plan = null;
$daySchedule = null;
$youtubeEnabled = false;

try {
    $stmt = $pdo->prepare('SELECT exam_title, exam_date, plan_json, generated_at FROM user_study_plans WHERE user_id = :user_id LIMIT 1');
    $stmt->execute(['user_id' => $user['id']]);
    $row = $stmt->fetch() ?: null;

    if (!$row) {
        $error = 'No plan found. Go back and generate your roadmap first.';
    } else {
        $planJson = json_decode((string) ($row['plan_json'] ?? ''), true);
        $weekly = is_array($planJson['weekly_plan'] ?? null) ? ($planJson['weekly_plan'] ?? []) : [];

        $plan = [
            'exam_title' => (string) ($row['exam_title'] ?? ''),
            'exam_date' => (string) ($row['exam_date'] ?? ''),
            'generated_at' => (string) ($row['generated_at'] ?? ''),
            'weekly_plan' => $weekly,
        ];

        $youtubeEnabled = (getYouTubeApiKey() !== '');

        if ($weekParam <= 0 || $dayParam === '') {
            $error = 'Missing parameters. Use the dashboard day links.';
        } else {
            foreach ($weekly as $week) {
                if (!is_array($week)) {
                    continue;
                }
                $w = (int) ($week['week'] ?? 0);
                if ($w !== $weekParam) {
                    continue;
                }
                $sessions = is_array($week['sessions'] ?? null) ? ($week['sessions'] ?? []) : [];
                foreach ($sessions as $session) {
                    if (!is_array($session)) {
                        continue;
                    }
                    $d = (string) ($session['day'] ?? '');
                    if (strcasecmp($d, $dayParam) !== 0) {
                        continue;
                    }
                    $blocks = is_array($session['blocks'] ?? null) ? ($session['blocks'] ?? []) : [];
                    $daySchedule = [
                        'week' => $w,
                        'day' => $d,
                        'focus' => (string) ($week['focus'] ?? ''),
                        'blocks' => $blocks,
                    ];
                    break 2;
                }
            }

            if (!$daySchedule) {
                $error = 'No schedule found for that day. Generate a new plan and try again.';
            }
        }
    }
} catch (Throwable $e) {
    $error = 'Unable to load your plan right now.';
}

$fullName = (string) ($user['full_name'] ?? 'Student');
$initial = strtoupper(substr($fullName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyGuru | Day plan</title>
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
                <span class="dashboard-user-label">Day plan</span>
                <strong><?php echo htmlspecialchars($fullName, ENT_QUOTES); ?></strong>
            </div>
            <div class="dashboard-avatar">
                <span><?php echo htmlspecialchars($initial, ENT_QUOTES); ?></span>
            </div>
            <a class="ghost" href="dashboard.php">Back</a>
        </div>
    </nav>

    <main class="dashboard-shell">
        <section class="dashboard-card">
            <p class="eyebrow">Daily schedule</p>
            <?php if ($error): ?>
                <div class="auth-alert auth-alert--error"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
            <?php else: ?>
                <h2>Week <?php echo htmlspecialchars((string) ($daySchedule['week'] ?? ''), ENT_QUOTES); ?> · <?php echo htmlspecialchars((string) ($daySchedule['day'] ?? ''), ENT_QUOTES); ?></h2>
                <?php if (!empty($plan['exam_title'])): ?>
                    <p class="dashboard-note">Exam: <strong><?php echo htmlspecialchars((string) $plan['exam_title'], ENT_QUOTES); ?></strong></p>
                <?php endif; ?>
                <?php if (!empty($daySchedule['focus'])): ?>
                    <p class="dashboard-note">Focus: <?php echo htmlspecialchars((string) $daySchedule['focus'], ENT_QUOTES); ?></p>
                <?php endif; ?>

                <div class="planner-output" style="margin-top: 1rem;">
                    <div class="planner-output-block planner-output-block--roadmap">
                        <h3>Today’s blocks</h3>
                        <ul class="planner-blocks">
                            <?php foreach (($daySchedule['blocks'] ?? []) as $block): ?>
                                <?php if (!is_array($block)) { continue; } ?>
                                <?php
                                    $subject = trim((string) ($block['subject'] ?? ''));
                                    $topic = trim((string) ($block['topic'] ?? ''));
                                    $detail = trim((string) ($block['topic_detail'] ?? ''));
                                    $minutes = (int) ($block['minutes'] ?? 0);
                                    $activity = trim((string) ($block['activity'] ?? ''));
                                    if ($subject === '' || $minutes <= 0) {
                                        continue;
                                    }
                                ?>
                                <li class="planner-block">
                                    <span class="planner-block-title"><?php echo htmlspecialchars($subject, ENT_QUOTES); ?></span>
                                    <?php if ($topic !== ''): ?>
                                        <?php
                                            $videoUrl = '';
                                            if ($youtubeEnabled) {
                                                $q = studyguruBuildYouTubeQuery((string) ($plan['exam_title'] ?? ''), $subject, $topic);
                                                $videos = $q !== '' ? youtubeSearchVideos($q, 1) : [];
                                                if (!empty($videos) && is_array($videos[0] ?? null)) {
                                                    $videoUrl = (string) ($videos[0]['url'] ?? '');
                                                }
                                            }
                                        ?>
                                        <div class="planner-block-topic-row">
                                            <span class="planner-block-topic"><?php echo htmlspecialchars($topic, ENT_QUOTES); ?></span>
                                            <?php if ($videoUrl !== ''): ?>
                                                <a class="solid planner-video-btn" href="<?php echo htmlspecialchars($videoUrl, ENT_QUOTES); ?>" target="_blank" rel="noopener">YouTube Video</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($detail !== ''): ?>
                                        <span class="planner-block-detail"><?php echo htmlspecialchars($detail, ENT_QUOTES); ?></span>
                                    <?php endif; ?>
                                    <span class="planner-block-meta"><?php echo htmlspecialchars((string) $minutes, ENT_QUOTES); ?> min<?php echo $activity !== '' ? ' · ' . htmlspecialchars($activity, ENT_QUOTES) : ''; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
