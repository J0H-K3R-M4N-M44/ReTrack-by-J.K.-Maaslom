<?php
$guides = require __DIR__ . '/../../app/learning/guides.php';
$staffGuide = $guideRole !== 'CUSTOMER';
[$label, $steps] = $guides[$guideRole] ?? $guides['VIEWER'];
$simulations = require __DIR__ . '/../../app/learning/simulations.php';
$simulation = $simulations[$guideRole] ?? null;
$root = $staffGuide ? '../' : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($label) ?> learning center | RepairTrack</title>
    <link rel="stylesheet" href="<?= $root ?>css/base.css">
    <link rel="stylesheet" href="<?= $root ?>css/<?= $staffGuide ? 'dashboard' : 'public' ?>.css">
    <?php if ($staffGuide): ?>
    <link rel="stylesheet" href="../css/workspace-design.css">
    <script src="../js/workspace-design.js" defer></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= $root ?>css/learning.css">
    <script src="<?= $root ?>js/learning.js" defer></script>
</head>
<body class="<?= $staffGuide ? 'dashboard-body rt-layout' : 'public-body' ?>">
<?php if ($staffGuide):
    $workspaceUser = $user;
    $currentSection = 'learning';
    require __DIR__ . '/../workspace/navigation.php';
else: ?>
    <header class="public-header"><a href="index.php">RepairTrack</a><nav aria-label="Customer navigation"><a href="track.php">Track a repair</a></nav></header>
<?php endif; ?>
<main id="rt-main" tabindex="-1" class="learning-main <?= $staffGuide ? 'rt-content' : '' ?>">
    <header class="learning-heading">
        <p><?= h($label) ?> guide</p>
        <h1>Learn RepairTrack</h1>
        <p>Read the steps below or practice with fictional repairs. Replay whenever you like.</p>
        <nav aria-label="Learning options"><a href="#manual">Read the manual</a><a href="#practice">Try the walkthrough</a></nav>
    </header>
    <section id="practice" class="learning-card" aria-labelledby="practice-heading">
        <h2 id="practice-heading">Practice walkthrough</h2>
        <noscript><p>Enable JavaScript to try the walkthrough. The manual below is available now.</p></noscript>
        <button type="button" id="practice-start" hidden>Start practice</button>
        <?php foreach ($steps as $index => [$title, $instructions, $scenario, $correct, $other, $feedback]): ?>
        <article class="practice-step" hidden>
            <p>Step <?= $index + 1 ?> of <?= count($steps) ?></p>
            <h3 tabindex="-1"><?= h($title) ?></h3>
            <p><?= h($scenario) ?></p>
            <?php if ($simulation && $simulation[0] === $index): ?>
                <?php [, $record, $fields, $action, $result] = $simulation; ?>
                <div class="practice-record"><strong>Sample record</strong><p><?= h($record) ?></p></div>
                <form class="practice-form" autocomplete="off">
                    <?php foreach ($fields as $field): ?>
                        <?php [$fieldLabel, $control, $expected] = $field; ?>
                        <label><?= h($fieldLabel) ?>
                            <?php if (is_array($control)): ?>
                                <select data-expected="<?= h($expected) ?>" required>
                                    <option value="">Choose an option</option>
                                    <?php foreach ($control as $option): ?>
                                        <option><?= h($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" inputmode="<?= h($field[3] ?? 'text') ?>"
                                    data-expected="<?= h($expected) ?>" maxlength="32" required>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                    <button type="submit"><?= h($action) ?></button>
                </form>
                <p class="practice-result" hidden><?= h($result) ?></p>
            <?php else: ?>
            <div class="practice-choices">
                <?php foreach ($index % 2 === 0 ? [[$correct, true], [$other, false]] : [[$other, false], [$correct, true]] as [$choice, $isCorrect]): ?>
                <button type="button" data-answer="<?= $isCorrect ? 'correct' : 'retry' ?>"><?= h($choice) ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <p class="practice-feedback" role="status" data-explanation="<?= h($feedback) ?>"></p>
            <details class="practice-hint"><summary>Show a hint</summary><p><?= h($feedback) ?></p></details>
            <button type="button" class="practice-next" hidden><?= $index === count($steps) - 1 ? 'Finish practice' : 'Next step' ?></button>
        </article>
        <?php endforeach; ?>
        <p id="practice-complete" tabindex="-1" hidden>Practice complete. You can start again or use the manual as a reference.</p>
    </section>
    <section id="manual" class="learning-card" aria-labelledby="manual-heading">
        <h2 id="manual-heading">Beginner manual</h2>
        <?php if ($staffGuide): ?>
            <p>Sign in with your staff account. Open <strong>Menu → Learning center</strong> to return here. Use the page search in Menu to find a destination. Trouble signing in? Start with Forgot password on the staff sign-in screen or contact your administrator.</p>
        <?php endif; ?>
        <ol>
            <?php foreach ($steps as [$title, $instructions]): ?>
                <li><h3><?= h($title) ?></h3><p><?= h($instructions) ?></p></li>
            <?php endforeach; ?>
        </ol>
    </section>
</main>
</body>
</html>
