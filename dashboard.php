<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['auth_user']['id'])) {
    header('Location: auth.php?mode=signin');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/gemini.php';

function studyguruIsGeminiQuotaOrRateLimit(string $message): bool
{
    $m = strtolower($message);
    return str_contains($m, 'quota exceeded')
        || str_contains($m, 'resource_exhausted')
        || str_contains($m, 'free tier limit')
        || str_contains($m, 'rate limit')
        || str_contains($m, 'too many requests');
}

/**
 * @return array<int,array{name:string,priority:string,notes:string}>
 */
function studyguruFallbackSubjects(string $examTitle): array
{
    $title = trim($examTitle);
    $key = $title;
    if (str_contains($title, '·')) {
        $key = trim(explode('·', $title, 2)[0]);
    }
    $keyLower = strtolower($key);

    $mk = static function (string $name, string $priority, string $notes = ''): array {
        return ['name' => $name, 'priority' => $priority, 'notes' => $notes];
    };

    if (str_contains($keyLower, 'cat')) {
        return [
            $mk('Quantitative Aptitude', 'high', 'Arithmetic, Algebra, Geometry, Modern Math.'),
            $mk('Verbal Ability & Reading Comprehension', 'high', 'RC practice + verbal fundamentals.'),
            $mk('Data Interpretation & Logical Reasoning', 'high', 'Sets + timed practice.'),
        ];
    }

    if (str_contains($keyLower, 'jee')) {
        return [
            $mk('Physics', 'high', 'Concepts + problem solving.'),
            $mk('Chemistry', 'high', 'Physical + Organic + Inorganic.'),
            $mk('Mathematics', 'high', 'Core chapters + mixed practice.'),
        ];
    }

    if (str_contains($keyLower, 'neet')) {
        return [
            $mk('Biology (Botany)', 'high', 'NCERT focus + diagrams.'),
            $mk('Biology (Zoology)', 'high', 'NCERT focus + PYQs.'),
            $mk('Chemistry', 'high', 'NCERT + numericals + reactions.'),
            $mk('Physics', 'medium', 'Formulas + numericals.'),
        ];
    }

    if (str_contains($keyLower, 'gate')) {
        return [
            $mk('General Aptitude', 'high', 'Scoring section; practice weekly.'),
            $mk('Engineering Mathematics', 'high', 'Core formulas + PYQs.'),
            $mk('Core Subject (as per your branch)', 'high', 'Pick your branch syllabus and follow it strictly.'),
        ];
    }

    if (str_contains($keyLower, 'upsc')) {
        return [
            $mk('Indian Polity', 'high', 'Laxmikanth + PYQs.'),
            $mk('Modern History', 'high', 'Spectrum + PYQs.'),
            $mk('Geography', 'high', 'Maps + NCERT + current.'),
            $mk('Economy', 'high', 'Basics + current affairs integration.'),
            $mk('Environment & Ecology', 'medium', 'Static + current.'),
            $mk('Science & Tech', 'medium', 'High-yield current topics.'),
            $mk('Current Affairs', 'high', 'Daily + weekly consolidation.'),
            $mk('CSAT', 'medium', 'Qualifying; consistent practice.'),
            $mk('Optional Subject', 'high', 'Define optional and allocate weekly depth.'),
        ];
    }

    if (str_contains($keyLower, 'ssc')) {
        return [
            $mk('Quantitative Aptitude', 'high', 'Speed + accuracy drills.'),
            $mk('Reasoning', 'high', 'Daily sets + mock analysis.'),
            $mk('English', 'medium', 'Grammar + vocab + RC.'),
            $mk('General Awareness', 'high', 'Static + current affairs.'),
        ];
    }

    if (str_contains($keyLower, 'ibps') || str_contains($keyLower, 'bank')) {
        return [
            $mk('Quantitative Aptitude', 'high', 'Timed practice + shortcuts.'),
            $mk('Reasoning', 'high', 'Puzzles + basics daily.'),
            $mk('English', 'medium', 'RC + error spotting.'),
            $mk('General / Banking Awareness', 'high', 'Monthly current + banking terms.'),
            $mk('Computer Awareness', 'low', 'Basics + MCQs.'),
        ];
    }

    if (str_contains($keyLower, 'clat')) {
        return [
            $mk('English Language', 'high', 'RC + vocab.'),
            $mk('Current Affairs (incl. GK)', 'high', 'Daily + weekly revision.'),
            $mk('Legal Reasoning', 'high', 'Principle-fact questions + practice.'),
            $mk('Logical Reasoning', 'high', 'Critical reasoning sets.'),
            $mk('Quantitative Techniques', 'medium', 'Arithmetic basics + sets.'),
        ];
    }

    if (str_contains($keyLower, 'cuet')) {
        return [
            $mk('Language Test', 'high', 'Reading + grammar + vocab.'),
            $mk('Domain Subjects (as per your course)', 'high', 'Select domains and follow syllabus strictly.'),
            $mk('General Test', 'medium', 'Reasoning + quantitative + general awareness.'),
        ];
    }

    return [
        $mk('Core Concepts', 'high', 'Build fundamentals for your exam.'),
        $mk('Practice & PYQs', 'high', 'Solve past papers and analyze errors.'),
        $mk('Revision', 'medium', 'Weekly revision + short notes.'),
    ];
}

/**
 * @return array<string,array<int,array{name:string,detail:string}>> Map: subject => topics
 */
function studyguruFallbackTopics(string $examTitle): array
{
    $title = trim($examTitle);
    $key = $title;
    if (str_contains($title, '·')) {
        $key = trim(explode('·', $title, 2)[0]);
    }
    $keyLower = strtolower($key);

    $t = static function (string $name, string $detail): array {
        return ['name' => $name, 'detail' => $detail];
    };

    if (str_contains($keyLower, 'cat')) {
        return [
            'Quantitative Aptitude' => [
                $t('Percentages & Profit/Loss', 'Concepts + 25 mixed questions, focus on speed.'),
                $t('Ratio & Proportion', 'Shortcuts + mixed problem set.'),
                $t('Time & Work / Pipes', 'Formula sheet + practice set.'),
                $t('Time-Speed-Distance', 'Units + trains/boats problems.'),
                $t('Algebra Basics', 'Linear/quadratic equations basics + practice.'),
                $t('Number Systems', 'Divisibility, remainders, factors.'),
                $t('Geometry & Mensuration', 'Key theorems + area/volume questions.'),
                $t('Modern Math (P&C/Probability)', 'Fundamentals + PYQ-style questions.'),
            ],
            'Verbal Ability & Reading Comprehension' => [
                $t('Reading Comprehension (RC)', '2 passages timed + review mistakes.'),
                $t('Para-jumbles', 'Practice sets + pattern recognition.'),
                $t('Summary / Para-summary', 'Main idea extraction drills.'),
                $t('Grammar & Usage', 'Error spotting + sentence correction.'),
                $t('Vocabulary in Context', 'Contextual meaning + usage.'),
            ],
            'Data Interpretation & Logical Reasoning' => [
                $t('DI Sets (Tables/Charts)', '1–2 sets timed + accuracy review.'),
                $t('LR Puzzles', 'Arrangement + constraints practice.'),
                $t('Games & Tournaments', 'Common LR patterns + solved examples.'),
                $t('Venn/Grouping', 'Set-based reasoning drills.'),
                $t('Logical Deductions', 'Statement-based deductions + practice.'),
            ],
        ];
    }

    if (str_contains($keyLower, 'jee')) {
        return [
            'Physics' => [
                $t('Kinematics', 'Key equations + 20 mixed problems.'),
                $t('Laws of Motion', 'Free body diagrams + friction basics.'),
                $t('Work, Energy & Power', 'Energy conservation + practice set.'),
                $t('Rotational Motion', 'Torque, angular momentum basics.'),
                $t('Electrostatics', 'Coulomb + fields + potential.'),
                $t('Current Electricity', 'Circuits + Kirchhoff + numericals.'),
                $t('Magnetism', 'Forces, induction overview + questions.'),
                $t('Ray Optics', 'Lenses/mirrors + sign conventions.'),
            ],
            'Chemistry' => [
                $t('Mole Concept', 'Stoichiometry drills + PYQs.'),
                $t('Chemical Bonding', 'VSEPR + hybridization + shapes.'),
                $t('Thermodynamics', 'ΔH/ΔG basics + numericals.'),
                $t('Equilibrium', 'Ionic + chemical equilibrium practice.'),
                $t('Organic: GOC', 'Inductive/resonance + acidity/basicity.'),
                $t('Organic: Hydrocarbons', 'Reactions + mechanism basics.'),
                $t('Inorganic: Periodic Trends', 'Trends + quick revision.'),
                $t('Inorganic: Coordination', 'Nomenclature + isomerism basics.'),
            ],
            'Mathematics' => [
                $t('Quadratic Equations', 'Roots/graphs + mixed questions.'),
                $t('Sequences & Series', 'AP/GP basics + practice.'),
                $t('Trigonometry', 'Identities + equations + practice.'),
                $t('Complex Numbers', 'Argand plane + operations.'),
                $t('Matrices & Determinants', 'Properties + solving systems.'),
                $t('Differentiation', 'Rules + maxima/minima.'),
                $t('Integration', 'Standard integrals + practice.'),
                $t('Vector & 3D', 'Basics + distance/angle problems.'),
            ],
        ];
    }

    if (str_contains($keyLower, 'neet')) {
        return [
            'Biology (Botany)' => [
                $t('Cell: Structure & Function', 'NCERT reading + diagrams.'),
                $t('Plant Physiology', 'Key processes + MCQs.'),
                $t('Genetics (Basics)', 'Mendelian + pedigree basics.'),
                $t('Ecology', 'NCERT facts + PYQs.'),
                $t('Plant Diversity', 'Classification + key examples.'),
            ],
            'Biology (Zoology)' => [
                $t('Human Physiology', 'Systems + high-yield MCQs.'),
                $t('Reproduction', 'NCERT focus + diagrams.'),
                $t('Evolution', 'Concepts + examples.'),
                $t('Biotechnology', 'Processes + applications.'),
                $t('Animal Diversity', 'Classification + key traits.'),
            ],
            'Chemistry' => [
                $t('Mole Concept', 'Numericals + NCERT examples.'),
                $t('Chemical Bonding', 'Shapes + hybridization + MCQs.'),
                $t('Equilibrium', 'Buffers + ionic eq practice.'),
                $t('Organic Basics', 'GOC + reactions overview.'),
                $t('Inorganic NCERT', 'Trends + memorization checklist.'),
            ],
            'Physics' => [
                $t('Units & Dimensions', 'Basics + error analysis.'),
                $t('Kinematics', 'Formula use + numericals.'),
                $t('Current Electricity', 'Circuits + numericals.'),
                $t('Ray Optics', 'Lenses/mirrors + numericals.'),
                $t('Modern Physics', 'Photoelectric + atomic basics.'),
            ],
        ];
    }

    if (str_contains($keyLower, 'upsc')) {
        return [
            'Indian Polity' => [
                $t('Constitution Basics', 'Preamble, FR/DPSP, basic structure.'),
                $t('Parliament', 'Functions, procedures, committees.'),
                $t('Judiciary', 'SC/HC powers + key doctrines.'),
                $t('Federalism', 'Centre-state relations + bodies.'),
                $t('Governance', 'Transparency, RTI, citizen charter.'),
            ],
            'Modern History' => [
                $t('1857 & After', 'Causes, course, consequences.'),
                $t('National Movement', '1885–1947 timeline + themes.'),
                $t('Social Reform', 'Key movements and leaders.'),
            ],
            'Geography' => [
                $t('Physical Geography', 'Climatology + geomorphology basics.'),
                $t('Indian Geography', 'Rivers, monsoon, resources.'),
                $t('Map Work', 'Daily 15 min map practice.'),
            ],
            'Economy' => [
                $t('Basics: GDP/Inflation', 'Definitions + indicators.'),
                $t('Budget & Fiscal Policy', 'Terms + analysis practice.'),
                $t('Banking & Monetary Policy', 'RBI tools + recent changes.'),
            ],
            'Current Affairs' => [
                $t('Daily News + Notes', 'Daily reading + weekly revision.'),
                $t('Monthly Compilation', 'Make short notes + PYQ linkage.'),
            ],
        ];
    }

    // Generic fallback: provide topics for common generic subjects.
    return [
        'Core Concepts' => [
            $t('Fundamentals', 'Read basics + make short notes.'),
            $t('Examples', 'Solve example problems/questions.'),
            $t('Practice Set', 'Timed practice + review mistakes.'),
        ],
        'Practice & PYQs' => [
            $t('PYQ Drill', 'Solve past questions + analyze errors.'),
            $t('Mock Section', 'Timed section test + review.'),
        ],
        'Revision' => [
            $t('Short Notes', 'Revise notes + error log.'),
            $t('Flash Revision', 'Spaced repetition of key points.'),
        ],
    ];
}

/**
 * @param array{daily_hours:float,days_per_week:int,session_minutes:int,strengths:string,weaknesses:string} $profile
 * @return array{exam:array{title:string,date:string},subjects:array<int,array{name:string,priority:string,notes:string}>,weekly_plan:array<int,array{week:int,focus:string,goals:array<int,string>,sessions:array<int,array{day:string,blocks:array<int,array{subject:string,topic:string,topic_detail:string,minutes:int,activity:string}>}>}>,revision_strategy:array<int,string>,mock_test_strategy:array<int,string>,meta:array{generated_by:string,reason:string}}
 */
function studyguruBuildFallbackPlan(string $examTitle, string $examDate, int $daysUntil, array $profile, string $reason): array
{
    $subjects = studyguruFallbackSubjects($examTitle);
     $topicLibrary = studyguruFallbackTopics($examTitle);
    $weeks = (int) max(1, (int) ceil($daysUntil / 7));
    $weeks = (int) min(20, $weeks);

    $daysPerWeek = (int) max(1, min(7, (int) ($profile['days_per_week'] ?? 6)));
    $sessionMinutes = (int) max(20, min(240, (int) ($profile['session_minutes'] ?? 60)));
    $dailyMinutes = (int) round(max(30.0, min(12.0, (float) ($profile['daily_hours'] ?? 2.0))) * 60);
    $blocksPerDay = (int) max(1, (int) floor($dailyMinutes / $sessionMinutes));
    $blocksPerDay = (int) min(4, $blocksPerDay);

    $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $studyDays = array_slice($dayNames, 0, $daysPerWeek);

    $weighted = [];
    foreach ($subjects as $subject) {
        $name = (string) ($subject['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $priority = (string) ($subject['priority'] ?? 'medium');
        $weight = 2;
        if ($priority === 'high') {
            $weight = 3;
        } elseif ($priority === 'low') {
            $weight = 1;
        }
        for ($i = 0; $i < $weight; $i++) {
            $weighted[] = $name;
        }
    }
    if (empty($weighted)) {
        $weighted = ['General Study'];
    }

    $pickActivity = static function (string $phase): string {
        return match ($phase) {
            'foundation' => 'Concepts + notes',
            'practice' => 'Problem solving / PYQs',
            'revision' => 'Revision + error log',
            'mock' => 'Mock test + analysis',
            default => 'Focused study',
        };
    };

    $defaultTopicsByPhase = static function (string $phase): array {
        return match ($phase) {
            'foundation' => ['Core concepts', 'Definitions + examples', 'Short notes'],
            'practice' => ['Timed practice set (PYQs)', 'Mixed question drill', 'Speed + accuracy'],
            'revision' => ['Revise weak points', 'Error log review', 'Flash revision'],
            'mock' => ['Mock test', 'Mock analysis', 'Final revision'],
            default => ['Targeted study'],
        };
    };

    $weeklyPlan = [];
    $cursor = 0;
    for ($w = 1; $w <= $weeks; $w++) {
        $progress = $weeks === 1 ? 1.0 : ($w / $weeks);
        $phase = 'foundation';
        if ($progress >= 0.75 && $weeks >= 3) {
            $phase = 'revision';
        } elseif ($progress >= 0.55 && $weeks >= 4) {
            $phase = 'practice';
        }
        if ($weeks <= 2) {
            $phase = 'mock';
        }

        $focus = match ($phase) {
            'foundation' => 'Build fundamentals + short notes',
            'practice' => 'Timed practice + PYQs + accuracy',
            'revision' => 'Revision + weak-area repair',
            'mock' => 'Mocks + analysis + final revision',
            default => 'Study',
        };

        $topSubjects = array_slice(array_values(array_unique($weighted)), 0, 3);
        $goals = [
            'Complete core concepts for: ' . implode(', ', $topSubjects),
            'Do 3–5 timed sets and note mistakes.',
            'End week with a mini-test + revise weak areas.',
        ];
        if ($phase === 'revision' || $phase === 'mock') {
            $goals[] = 'Revise short notes + error log every day.';
        }

        $sessions = [];
        foreach ($studyDays as $day) {
            $blocks = [];
            $remaining = $dailyMinutes;
            for ($b = 0; $b < $blocksPerDay; $b++) {
                $minutes = (int) min($sessionMinutes, $remaining);
                if ($minutes <= 0) {
                    break;
                }
                $subjectName = $weighted[$cursor % count($weighted)];
                $cursor++;

                $topicName = '';
                $topicDetail = '';
                $subjectTopics = $topicLibrary[$subjectName] ?? [];
                if (is_array($subjectTopics) && !empty($subjectTopics)) {
                    $topicRow = $subjectTopics[$cursor % count($subjectTopics)] ?? null;
                    if (is_array($topicRow)) {
                        $topicName = (string) ($topicRow['name'] ?? '');
                        $topicDetail = (string) ($topicRow['detail'] ?? '');
                    }
                }
                if (trim($topicName) === '') {
                    $phaseTopics = $defaultTopicsByPhase($phase);
                    $topicName = (string) ($phaseTopics[$cursor % count($phaseTopics)] ?? 'Targeted study');
                    $topicDetail = 'Work through a focused set and note mistakes.';
                }

                $blocks[] = [
                    'subject' => $subjectName,
                    'topic' => $topicName,
                    'topic_detail' => $topicDetail,
                    'minutes' => $minutes,
                    'activity' => $pickActivity($phase),
                ];
                $remaining -= $minutes;
            }
            $sessions[] = ['day' => $day, 'blocks' => $blocks];
        }

        $weeklyPlan[] = [
            'week' => $w,
            'focus' => $focus,
            'goals' => $goals,
            'sessions' => $sessions,
        ];
    }

    return [
        'exam' => ['title' => $examTitle, 'date' => $examDate],
        'subjects' => $subjects,
        'weekly_plan' => $weeklyPlan,
        'revision_strategy' => [
            'Maintain a daily error-log and revise it weekly.',
            'Use spaced repetition for formulas/vocab/facts.',
            'Do a quick recap at the end of each study day.',
        ],
        'mock_test_strategy' => [
            'Start with sectional tests, then move to full-length mocks.',
            'Always analyze mocks: time sinks, accuracy, and weak topics.',
            'In the final phase, prioritize revision + mock analysis over new topics.',
        ],
        'meta' => [
            'generated_by' => 'fallback',
            'reason' => $reason,
        ],
    ];
}

$user = $_SESSION['auth_user'];
$pdo = null;
$exam = null;
$examErrors = [];
$examSuccess = null;
$examFormValues = ['exam_title' => '', 'exam_date' => ''];

$profileErrors = [];
$profileSuccess = null;
$profileFormValues = [
    'daily_hours' => '2.0',
    'days_per_week' => '6',
    'session_minutes' => '60',
    'strengths' => '',
    'weaknesses' => ''
];

$plannerErrors = [];
$plannerSuccess = null;
$studyPlan = null;

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare('SELECT id, full_name, email, provider, avatar_url, last_login_at, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $user['id']]);
    $record = $stmt->fetch() ?: [];
    if ($record) {
        $user = array_merge($user, $record);
        $_SESSION['auth_user'] = $user;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'exam') {
        $examTitleInput = trim($_POST['exam_title'] ?? '');
        $examDateInput = trim($_POST['exam_date'] ?? '');
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
        $examFormValues['exam_title'] = $examTitleInput;
        $examFormValues['exam_date'] = $examDateInput;

        if ($examTitleInput === '') {
            $examErrors[] = 'Exam name is required.';
        }

        $dateObject = DateTime::createFromFormat('Y-m-d', $examDateInput) ?: null;
        if (!$dateObject || $dateObject->format('Y-m-d') !== $examDateInput) {
            $examErrors[] = 'Choose a valid exam date.';
        }

        if (empty($examErrors)) {
            try {
                $saveExam = $pdo->prepare('INSERT INTO user_exams (user_id, exam_title, exam_date) VALUES (:user_id, :exam_title, :exam_date)
                    ON DUPLICATE KEY UPDATE exam_title = VALUES(exam_title), exam_date = VALUES(exam_date), updated_at = CURRENT_TIMESTAMP');
                $saveExam->execute([
                    'user_id' => $user['id'],
                    'exam_title' => $examTitleInput,
                    'exam_date' => $examDateInput
                ]);
                $examSuccess = 'Exam details saved.';
            } catch (Throwable $saveException) {
                $examErrors[] = 'Unable to save the exam right now. Please try again.';
            }
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
            try {
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
                $profileSuccess = 'Study preferences saved.';
            } catch (Throwable $saveProfileException) {
                $profileErrors[] = 'Unable to save study preferences right now.';
            }
        }
    }

    $examStmt = $pdo->prepare('SELECT exam_title, exam_date FROM user_exams WHERE user_id = :user_id LIMIT 1');
    $examStmt->execute(['user_id' => $user['id']]);
    $exam = $examStmt->fetch() ?: null;
    if ($exam && empty($examErrors)) {
        $examFormValues = [
            'exam_title' => $exam['exam_title'],
            'exam_date' => $exam['exam_date']
        ];
    }

    $profileStmt = $pdo->prepare('SELECT daily_hours, days_per_week, session_minutes, strengths, weaknesses FROM user_study_profiles WHERE user_id = :user_id LIMIT 1');
    $profileStmt->execute(['user_id' => $user['id']]);
    $profile = $profileStmt->fetch() ?: null;
    if ($profile && empty($profileErrors)) {
        $profileFormValues = [
            'daily_hours' => (string) $profile['daily_hours'],
            'days_per_week' => (string) $profile['days_per_week'],
            'session_minutes' => (string) $profile['session_minutes'],
            'strengths' => (string) ($profile['strengths'] ?? ''),
            'weaknesses' => (string) ($profile['weaknesses'] ?? ''),
        ];
    }

    $planStmt = $pdo->prepare('SELECT exam_title, exam_date, subjects_json, plan_json, generated_at FROM user_study_plans WHERE user_id = :user_id LIMIT 1');
    $planStmt->execute(['user_id' => $user['id']]);
    $planRow = $planStmt->fetch() ?: null;
    if ($planRow) {
        $studyPlan = $planRow;
        $studyPlan['subjects'] = json_decode((string) $planRow['subjects_json'], true) ?: [];
        $studyPlan['plan'] = json_decode((string) $planRow['plan_json'], true) ?: [];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'generate_plan') {
        if (!$exam) {
            $plannerErrors[] = 'Save your exam first.';
        } else {
            $today = (new DateTimeImmutable('now'))->format('Y-m-d');
            $examTitleCurrent = (string) ($exam['exam_title'] ?? '');
            $examDateCurrent = (string) ($exam['exam_date'] ?? '');
            $daysUntil = max(0, (int) ceil((strtotime($examDateCurrent) - time()) / 86400));

            $profileContext = [
                'daily_hours' => (float) ($profileFormValues['daily_hours'] ?? '2.0'),
                'days_per_week' => (int) ($profileFormValues['days_per_week'] ?? '6'),
                'session_minutes' => (int) ($profileFormValues['session_minutes'] ?? '60'),
                'strengths' => (string) ($profileFormValues['strengths'] ?? ''),
                'weaknesses' => (string) ($profileFormValues['weaknesses'] ?? ''),
            ];

            // Gemini is disabled by request. Always generate offline roadmap.
            $usedFallback = true;
            $fallbackReason = 'Offline roadmap (Gemini disabled).';
            $data = studyguruBuildFallbackPlan($examTitleCurrent, $examDateCurrent, $daysUntil, $profileContext, $fallbackReason);

            if (!is_array($data)) {
                $plannerErrors[] = 'Unable to generate plan.';
            } else {
                $subjects = $data['subjects'] ?? [];
                $weeklyPlan = $data['weekly_plan'] ?? [];

                if (!is_array($subjects) || !is_array($weeklyPlan) || empty($subjects) || empty($weeklyPlan)) {
                    $plannerErrors[] = 'Plan output was incomplete. Try again.';
                } else {
                    try {
                        $savePlan = $pdo->prepare('INSERT INTO user_study_plans (user_id, exam_title, exam_date, subjects_json, plan_json)
                            VALUES (:user_id, :exam_title, :exam_date, :subjects_json, :plan_json)
                            ON DUPLICATE KEY UPDATE exam_title = VALUES(exam_title), exam_date = VALUES(exam_date), subjects_json = VALUES(subjects_json), plan_json = VALUES(plan_json), generated_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP');
                        $savePlan->execute([
                            'user_id' => $user['id'],
                            'exam_title' => $examTitleCurrent,
                            'exam_date' => $examDateCurrent,
                            'subjects_json' => json_encode($subjects, JSON_UNESCAPED_UNICODE),
                            'plan_json' => json_encode([
                                'weekly_plan' => $weeklyPlan,
                                'revision_strategy' => $data['revision_strategy'] ?? [],
                                'mock_test_strategy' => $data['mock_test_strategy'] ?? [],
                                'meta' => $data['meta'] ?? ['generated_by' => 'fallback', 'reason' => $fallbackReason],
                            ], JSON_UNESCAPED_UNICODE),
                        ]);

                        header('Location: plan.php?generated=1');
                        exit;
                    } catch (Throwable $savePlanException) {
                        $plannerErrors[] = 'Unable to save the generated plan.';
                    }
                }
            }
        }
    }
} catch (Throwable $exception) {
    error_log('StudyGuru dashboard error: ' . $exception->getMessage());
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'exam') {
        $examErrors[] = 'Database connection failed. Please try again shortly.';
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'study_profile') {
        $profileErrors[] = 'Database connection failed. Please try again shortly.';
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'generate_plan') {
        $plannerErrors[] = 'Database connection failed. Please try again shortly.';
    }
}

$fullName = $user['full_name'] ?? 'Pilot';
$firstName = explode(' ', trim($fullName))[0] ?: 'Pilot';
$provider = $user['provider'] ?? 'email';
$avatarUrl = $user['avatar_url'] ?? '';
$initial = strtoupper(substr($fullName, 0, 1));
$lastLogin = $user['last_login_at'] ?? null;
$memberSince = $user['created_at'] ?? null;
$hasExam = $exam && !empty($exam['exam_title']) && !empty($exam['exam_date']);
$examTitle = $hasExam ? $exam['exam_title'] : null;
$examDateFormatted = $hasExam ? date('l, d M Y', strtotime($exam['exam_date'])) : null;
$daysUntilExam = $hasExam ? max(0, (int) ceil((strtotime($exam['exam_date']) - time()) / 86400)) : null;

$hasRoadmap = false;
if (is_array($studyPlan) && is_array($studyPlan['plan'] ?? null)) {
    $weeklyPlanCandidate = $studyPlan['plan']['weekly_plan'] ?? null;
    $hasRoadmap = is_array($weeklyPlanCandidate) && !empty($weeklyPlanCandidate);
}

$renderProfileForm = static function (array $values, array $errors, ?string $success): void {
    ?>
    <?php if ($success): ?>
        <div class="auth-alert auth-alert--success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="auth-alert auth-alert--error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error, ENT_QUOTES); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="POST" class="dashboard-form dashboard-form--profile">
        <input type="hidden" name="form" value="study_profile">
        <label>
            Daily study hours
            <input type="number" step="0.5" min="0.5" max="12" name="daily_hours" value="<?php echo htmlspecialchars($values['daily_hours'] ?? '2.0', ENT_QUOTES); ?>" required>
        </label>
        <label>
            Days per week
            <input type="number" min="1" max="7" name="days_per_week" value="<?php echo htmlspecialchars($values['days_per_week'] ?? '6', ENT_QUOTES); ?>" required>
        </label>
        <label>
            Session length (minutes)
            <input type="number" min="20" max="240" name="session_minutes" value="<?php echo htmlspecialchars($values['session_minutes'] ?? '60', ENT_QUOTES); ?>" required>
        </label>
        <label>
            Strengths (optional)
            <input type="text" name="strengths" placeholder="e.g., Algebra, Reading comprehension" value="<?php echo htmlspecialchars($values['strengths'] ?? '', ENT_QUOTES); ?>">
        </label>
        <label>
            Weaknesses (optional)
            <input type="text" name="weaknesses" placeholder="e.g., Geometry, Time management" value="<?php echo htmlspecialchars($values['weaknesses'] ?? '', ENT_QUOTES); ?>">
        </label>
        <button type="submit">Save preferences</button>
    </form>
    <?php
};

$renderPlanner = static function (?array $studyPlan, array $errors, ?string $success): void {
    ?>
    <?php if ($success): ?>
        <div class="auth-alert auth-alert--success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="auth-alert auth-alert--error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error, ENT_QUOTES); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="dashboard-form dashboard-form--planner">
        <input type="hidden" name="form" value="generate_plan">
        <button type="submit">Generate / Refresh plan</button>
    </form>

    <?php if (!$studyPlan): ?>
        <p class="dashboard-note">No plan generated yet. Save preferences and generate your first plan.</p>
        <?php return; ?>
    <?php endif; ?>

    <?php
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

        $weeklyPlan = is_array($studyPlan['plan']['weekly_plan'] ?? null) ? ($studyPlan['plan']['weekly_plan'] ?? []) : [];
        $weekCount = count($weeklyPlan);
    ?>

    <div class="planner-kpis">
        <div class="planner-kpi"><span class="planner-kpi-label">Weeks</span><strong class="planner-kpi-value"><?php echo htmlspecialchars((string) $weekCount, ENT_QUOTES); ?></strong></div>
        <div class="planner-kpi"><span class="planner-kpi-label">Subjects</span><strong class="planner-kpi-value"><?php echo htmlspecialchars((string) count($subjectsAll), ENT_QUOTES); ?></strong></div>
        <div class="planner-kpi"><span class="planner-kpi-label">High priority</span><strong class="planner-kpi-value"><?php echo htmlspecialchars((string) count($subjectsHigh), ENT_QUOTES); ?></strong></div>
    </div>

    <div class="planner-output">
        <div class="planner-output-block planner-output-block--roadmap">
            <h3>Roadmap</h3>
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

            <?php
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

            <?php $renderSubjectRow('High priority', $subjectsHigh); ?>
            <?php $renderSubjectRow('Medium priority', $subjectsMedium); ?>
            <?php $renderSubjectRow('Low priority', $subjectsLow); ?>
        </div>
    </div>
    <?php
};

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

$renderExamForm = static function (array $values, array $errors, ?string $success, array $options): void {
    $currentExamValue = $values['exam_title'] ?? '';
    $currentExamDateValue = (string) ($values['exam_date'] ?? '');

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
    $optionList = $options;
    if ($currentExamValue && !in_array($currentExamValue, $optionList, true)) {
        array_unshift($optionList, $currentExamValue);
    }
    ?>
    <?php if ($success): ?>
        <div class="auth-alert auth-alert--success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="auth-alert auth-alert--error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error, ENT_QUOTES); ?></li>
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
                        <?php $value = sprintf('%02d', $d); ?>
                        <option value="<?php echo htmlspecialchars($value, ENT_QUOTES); ?>" <?php echo ($value === $dateDay) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $d, ENT_QUOTES); ?></option>
                    <?php endfor; ?>
                </select>

                <select name="exam_month" required>
                    <option value="">Month</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <?php $value = sprintf('%02d', $m); ?>
                        <option value="<?php echo htmlspecialchars($value, ENT_QUOTES); ?>" <?php echo ($value === $dateMonth) ? 'selected' : ''; ?>><?php echo htmlspecialchars(date('M', mktime(0, 0, 0, $m, 1)), ENT_QUOTES); ?></option>
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
        <button type="submit">Save exam</button>
    </form>
    <?php
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyGuru | Dashboard</title>
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
                <span class="dashboard-user-label">Signed in as</span>
                <strong><?php echo htmlspecialchars($fullName, ENT_QUOTES); ?></strong>
            </div>
            <div class="dashboard-avatar <?php echo $provider === 'google' ? 'dashboard-avatar--google' : ''; ?>">
                <?php if ($avatarUrl): ?>
                    <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($fullName, ENT_QUOTES); ?> profile photo">
                <?php elseif ($provider === 'google'): ?>
                    <span class="dashboard-google-badge" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="img" aria-label="Google profile">
                            <path fill="#EA4335" d="M12 10.2v3.9h5.4c-.24 1.26-.97 2.32-2.07 3.04l3.35 2.6c1.95-1.8 3.08-4.45 3.08-7.64 0-.73-.06-1.43-.18-2.1z" />
                            <path fill="#34A853" d="M6.64 14.32l-.99.76-2.67 2.07C4.27 19.88 7.89 22 12 22c2.7 0 4.97-.9 6.63-2.43l-3.35-2.6c-.9.6-2.05.97-3.28.97-2.53 0-4.68-1.7-5.44-4.03z" />
                            <path fill="#4A90E2" d="M3 7.85A9.97 9.97 0 0 0 2 12c0 1.57.36 3.06 1.01 4.38L6.64 14.3A5.96 5.96 0 0 1 6.2 12c0-.73.13-1.44.36-2.08z" />
                            <path fill="#FBBC05" d="M12 5.9c1.47 0 2.78.5 3.82 1.47l2.86-2.86C16.96 2.92 14.69 2 12 2 7.89 2 4.27 4.12 2.98 7.85L6.57 9.9C7.33 7.57 9.48 5.9 12 5.9z" />
                        </svg>
                    </span>
                <?php else: ?>
                    <span><?php echo htmlspecialchars($initial, ENT_QUOTES); ?></span>
                <?php endif; ?>
            </div>
            <a class="ghost" href="logout.php">Log out</a>
        </div>
    </nav>

    <main class="dashboard-shell">
        <?php if ($hasRoadmap): ?>
            <section class="dashboard-card dashboard-card--planner">
                <p class="eyebrow">Roadmap ready</p>
                <h2>Your roadmap is generated</h2>
                <p class="dashboard-note">Open your full plan page to view all steps, weekly roadmap, and daily topics.</p>
                <div class="nav-actions" style="margin-top: 1rem;">
                    <a class="solid" href="plan.php">Open plan</a>
                    <a class="ghost" href="goal.php">Change your goal</a>
                </div>
            </section>
        <?php else: ?>
            <section class="dashboard-card dashboard-card--exam">
                <p class="eyebrow">Step 1 · Exam</p>
                <h2><?php echo $hasExam ? htmlspecialchars((string) $examTitle, ENT_QUOTES) : 'Add your exam'; ?></h2>
                <p class="dashboard-note">
                    <?php if ($hasExam): ?>
                        Target date: <strong><?php echo htmlspecialchars((string) $examDateFormatted, ENT_QUOTES); ?></strong>
                        <?php if ($daysUntilExam !== null): ?> · <?php echo htmlspecialchars((string) $daysUntilExam, ENT_QUOTES); ?> days left<?php endif; ?>
                    <?php else: ?>
                        Select your exam and exam date to unlock the roadmap.
                    <?php endif; ?>
                </p>
                <?php $renderExamForm($examFormValues, $examErrors, $examSuccess, $examOptions); ?>
            </section>

            <section class="dashboard-card">
                <p class="eyebrow">Step 2 · Preferences</p>
                <h2>Study preferences</h2>
                <p class="dashboard-note">Set your hours, study days, and weak areas so the roadmap matches your routine.</p>
                <?php $renderProfileForm($profileFormValues, $profileErrors, $profileSuccess); ?>
            </section>

            <section class="dashboard-card dashboard-card--planner">
                <p class="eyebrow">Step 3 · Roadmap</p>
                <h2>Roadmap & subjects</h2>
                <p class="dashboard-note">
                    <?php if ($hasExam): ?>
                        Generate your roadmap. If Gemini is unavailable, StudyGuru will use an offline fallback.
                    <?php else: ?>
                        Save your exam first, then generate your roadmap.
                    <?php endif; ?>
                </p>
                <?php if ($hasExam): ?>
                    <?php $renderPlanner($studyPlan, $plannerErrors, $plannerSuccess); ?>
                <?php else: ?>
                    <p class="dashboard-note">Roadmap will appear here after you save your exam.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
