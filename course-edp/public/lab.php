<?php
declare(strict_types=1);
/** Separate course lab page available only to authenticated technicians. */
require_once __DIR__.'/../app/technician.php';
edpPreviewHeaders();
edpTechStartSession();
try {
    $pdo=edpPreviewDb();
    $staff=edpTechStaff($pdo);
    if (!$staff) {header('Location: technician.php?page=login',true,303);exit;}
} catch (Throwable $e) {
    error_log('EDP lab: '.get_class($e));
    http_response_code(503);
    exit('Academic tools are temporarily unavailable.');
}
$name=htmlspecialchars($staff['full_name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$year=(int)(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y');
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>EDP Lab Tools · RepairTrack Academic</title>
<link rel="stylesheet" href="assets/technician.css">
<link rel="stylesheet" href="assets/lab.css">
<script defer src="assets/lab.js"></script>
</head><body>
<header class="topbar"><a class="brand" href="technician.php">Repair<span>Track</span></a>
<span class="top-sub">EDP Lab Tools</span><div class="account"><?= $name ?> · <a href="technician.php">Back to repairs</a></div></header>
<main class="container lab" data-year="<?= $year ?>">
<header class="hero"><div><p class="eyebrow">Course exercises · local academic preview</p><h1>File events &amp; API integration</h1>
<p>Practice safely with fictional notes. No files are uploaded and nothing here changes a repair ticket.</p></div></header>
<div class="lab-grid">
<section class="panel" aria-labelledby="lab-notes-heading">
<h2 id="lab-notes-heading">Notepad: open &amp; save</h2>
<p class="hint">Open a local UTF-8 .txt file, edit it, and save to your own computer. This browser tool does not store data in RepairTrack.</p>
<label for="note-file-name">File name</label><input id="note-file-name" value="repair-notes.txt" maxlength="80" autocomplete="off">
<label for="note-editor">Text editor</label><textarea id="note-editor" rows="11" maxlength="100000" spellcheck="true" aria-describedby="note-feedback"></textarea>
<div class="lab-actions">
<button type="button" class="primary" id="note-open">Open .txt</button>
<button type="button" class="primary" id="note-save">Save .txt</button>
<button type="button" class="quiet" id="note-clear">Clear editor</button>
<input id="note-file-input" class="visually-hidden" type="file" accept=".txt,text/plain" aria-label="Choose a text file">
</div><p id="note-feedback" class="hint" role="status" aria-live="polite">Ready to edit.</p>
<div class="rule"><h3>Folder-change monitor</h3>
<p class="hint">Choose a local folder (where supported). Changes to .txt names are detected while this page remains open. The folder is never uploaded.</p>
<div class="lab-actions"><button type="button" class="quiet" id="watch-start">Monitor folder</button><button type="button" class="quiet" id="watch-stop" disabled>Stop</button></div>
<p id="watch-feedback" class="hint" role="status" aria-live="polite">Not monitoring.</p>
<ol id="watch-events" class="watch-events" aria-label="Observed text file changes"></ol>
<p class="hint">This browser monitor uses user-permitted directory polling. For the course's required <code>FileSystemWatcher</code> class, use the included Windows Forms Notepad companion source in <code>course-edp/labs/NotepadCompanion</code>.</p>
</div>
</section>
<section class="panel" aria-labelledby="lab-api-heading">
<h2 id="lab-api-heading">External REST/JSON: Philippine holidays</h2>
<p class="hint">This reference demonstrates HTTPS consumption, JSON parsing and display, asynchronous fetch events, and error/timeout handling. Holiday dates can help staff plan pickup expectations, but do not represent branch opening hours.</p>
<label for="holiday-year">Calendar year</label><select id="holiday-year"><option value="<?= $year ?>"><?= $year ?></option><option value="<?= $year+1 ?>"><?= $year+1 ?></option></select>
<div class="lab-actions"><button type="button" class="primary" id="holidays-load">Check holiday reference</button></div>
<p id="holiday-feedback" role="status" aria-live="polite">No external request made yet.</p>
<ul id="holiday-results" class="holiday-results" aria-label="Philippine public holidays"></ul>
<p class="hint">Source: <a href="https://date.nager.at" rel="noopener noreferrer">Nager.Date</a>. Availability depends on the external service; repair data is never transmitted.</p>
</section>
</div></main>
<footer>EDP classroom demonstration · Local-only · No access to customer files or private notes</footer>
</body></html>
