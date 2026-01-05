<?php
$navLinks = [
    ['label' => 'Why StudyGuru', 'href' => '#why'],
    ['label' => 'Workflow', 'href' => '#workflow'],
    ['label' => 'Planner', 'href' => '#planner'],
    ['label' => 'Insights', 'href' => '#insights']
];

$benefits = [
    [
        'title' => 'Adaptive study maps',
        'copy' => 'AI prioritizes chapters by mastery level and exam date, then auto-adjusts when your schedule shifts.',
        'stat' => '92%',
        'tag' => 'Retention boost'
    ],
    [
        'title' => 'Smart session briefs',
        'copy' => 'Each session ships with reading targets, question banks, and focus cues sourced from your syllabus.',
        'stat' => '18 min',
        'tag' => 'Prep time saved'
    ],
    [
        'title' => 'Progress that persuades',
        'copy' => 'Realtime velocity, streaks, and recovery prompts keep you accountable without feeling robotic.',
        'stat' => '4.7 / 5',
        'tag' => 'Consistency score'
    ]
];

$workflow = [
    [
        'step' => '01',
        'title' => 'Ingest your syllabus',
        'detail' => 'Upload PDFs, URLs, or paste bullet notes. StudyGuru parses scope, weightage, and deadlines.'
    ],
    [
        'step' => '02',
        'title' => 'Calibrate focus',
        'detail' => 'Tell the AI what a productive day looks like. It blends deep work blocks with micro-reviews.'
    ],
    [
        'step' => '03',
        'title' => 'Run the planner',
        'detail' => 'Generate a living plan that rebalances automatically if you skip, sprint, or get ahead.'
    ],
    [
        'step' => '04',
        'title' => 'Review insights',
        'detail' => 'Digest weekly recaps, bottleneck alerts, and tactical nudges inside the insights board.'
    ]
];

$insights = [
    ['label' => 'Focus score', 'value' => '87', 'suffix' => '/100'],
    ['label' => 'Weekly hours locked', 'value' => '14.2', 'suffix' => ' hrs'],
    ['label' => 'Recovery days planned', 'value' => '2'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="StudyGuru is an AI-native study planner for ambitious learners who want adaptive schedules, smart insights, and consistent focus.">
    <title>StudyGuru | AI Study Planner</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<section class="hero" id="top">
    <div class="glow"></div>
    <nav class="nav">
        <div class="logo">StudyGuru</div>
        <ul>
            <?php foreach ($navLinks as $link): ?>
                <li><a href="<?php echo $link['href']; ?>"><?php echo $link['label']; ?></a></li>
            <?php endforeach; ?>
        </ul>
        <div class="nav-actions">
            <a class="ghost" href="auth.php?mode=signin">Sign in</a>
            <a class="solid" href="auth.php?mode=signup">Sign up</a>
        </div>
    </nav>

    <div class="hero-body">
        <div class="hero-copy">
            <span class="eyebrow">AI-native study planner</span>
            <h1>
                Study smarter with <span class="accent" data-phrases='["adaptive schedules","bias-free insights","real accountability"]'>adaptive schedules</span> in one click.
            </h1>
            <p>
                StudyGuru blends syllabus intelligence, calendar availability, and focus science to craft a living plan that flexes with you. No more static timetables or guilty backlogs.
            </p>
            <div class="hero-ctas">
                <a class="solid" href="#planner">Build my plan</a>
                <a class="ghost" href="#workflow">See how it works</a>
            </div>
            <div class="hero-meta">
                <div>
                    <strong>8k+</strong>
                    <span>Plans shipped</span>
                </div>
                <div>
                    <strong>3.6x</strong>
                    <span>Better exam readiness</span>
                </div>
                <div>
                    <strong>120</strong>
                    <span>Institutes onboard</span>
                </div>
            </div>
        </div>
        <div class="hero-panel">
            <div class="panel-card focus">
                <p>Next deep work block</p>
                <h3>Quantitative Aptitude</h3>
                <ul>
                    <li>45 min focus sprint</li>
                    <li>10 review questions</li>
                    <li>2 min reflection</li>
                </ul>
                <button>Start block</button>
            </div>
            <div class="panel-card score">
                <header>
                    <span>Consistency streak</span>
                    <strong>+4 days</strong>
                </header>
                <div class="chart">
                    <span style="height: 70%"></span>
                    <span style="height: 90%"></span>
                    <span style="height: 50%"></span>
                    <span style="height: 100%"></span>
                    <span style="height: 65%"></span>
                </div>
                <footer>Recovery day scheduled for Friday</footer>
            </div>
        </div>
    </div>
</section>

<section class="benefits" id="why">
    <div class="section-intro">
        <p class="eyebrow">Why StudyGuru</p>
        <h2>Professional planning, minus the spreadsheet drama.</h2>
        <p>Give the AI your syllabus, energy curve, and exam dates. It handles the calculus of prioritizing, pacing, and recovering.</p>
    </div>
    <div class="cards">
        <?php foreach ($benefits as $benefit): ?>
            <article>
                <span class="tag"><?php echo $benefit['tag']; ?></span>
                <h3><?php echo $benefit['title']; ?></h3>
                <p><?php echo $benefit['copy']; ?></p>
                <div class="stat">
                    <strong><?php echo $benefit['stat']; ?></strong>
                    <span>Avg. result</span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="workflow" id="workflow">
    <div class="section-intro">
        <p class="eyebrow">Workflow</p>
        <h2>A calm pipeline from syllabus chaos to study certainty.</h2>
    </div>
    <div class="timeline">
        <?php foreach ($workflow as $item): ?>
            <div class="timeline-card">
                <span class="step"><?php echo $item['step']; ?></span>
                <h3><?php echo $item['title']; ?></h3>
                <p><?php echo $item['detail']; ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="planner" id="planner">
    <div class="planner-shell">
        <div class="planner-copy">
            <p class="eyebrow">Planner preview</p>
            <h2>Feed the AI a course, get a real study schedule within seconds.</h2>
            <p>Drop a course, target date, and desired pace. StudyGuru assembles intervals, question banks, and built-in recovery days.</p>
            <ul class="highlights">
                <li>Blends school schedule, work hours, and rest nodes automatically.</li>
                <li>Exports to Google Calendar, Notion, or plain PDF in one tap.</li>
                <li>Signals when you are over capacity before burnout hits.</li>
            </ul>
        </div>
        <form class="planner-form">
            <label>
                Course or exam focus
                <input type="text" placeholder="E.g., CFA Level 1, JEE Physics">
            </label>
            <label>
                Target exam date
                <input type="date">
            </label>
            <label>
                Weekly hours available
                <input type="number" min="1" max="80" placeholder="12">
            </label>
            <label>
                Focus style
                <select>
                    <option value="deep">Deep focus blocks</option>
                    <option value="balanced">Balanced schedule</option>
                    <option value="micro">Micro sessions</option>
                </select>
            </label>
            <button type="submit">Generate preview</button>
            <p class="disclaimer">Your entries stay on this device until you sync with StudyGuru.</p>
        </form>
    </div>
</section>

<section class="insights" id="insights">
    <div class="section-intro">
        <p class="eyebrow">Insights board</p>
        <h2>Clarity dashboards distilled for serious learners.</h2>
    </div>
    <div class="insight-grid">
        <?php foreach ($insights as $block): ?>
            <article>
                <span><?php echo $block['label']; ?></span>
                <strong><?php echo $block['value']; ?><?php echo isset($block['suffix']) ? $block['suffix'] : ''; ?></strong>
            </article>
        <?php endforeach; ?>
        <article>
            <span>Upcoming checkpoints</span>
            <ul>
                <li>Mock exam dry run · Sunday 08:00</li>
                <li>Group review sprint · Tuesday 19:30</li>
                <li>Reflection window · Friday 21:00</li>
            </ul>
        </article>
        <article>
            <span>Accountability partner</span>
            <p>Share your plan with mentors or peers. Automated nudges keep everyone aligned.</p>
            <a href="mailto:hello@studyguru.ai">Request access</a>
        </article>
    </div>
</section>

<footer class="footer">
    <div>
        <strong>StudyGuru</strong>
        <p>AI study planning built for ambitious students, bootcamps, and institutes.</p>
    </div>
    <div class="footer-links">
        <a href="mailto:hello@studyguru.ai">Contact</a>
        <a href="#top">Back to top</a>
        <a href="https://studyguru.ai" target="_blank" rel="noopener">Docs</a>
    </div>
</footer>

<script src="assets/js/app.js" defer></script>
</body>
</html>
