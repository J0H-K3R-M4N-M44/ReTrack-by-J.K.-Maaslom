<?php
declare(strict_types=1);

require_once __DIR__ . '/recoveries.php';

/**
 * Keep investigation evidence in a dedicated directory OUTSIDE the application
 * and document root. No public URL maps to this directory.
 */
function stockEvidenceDirectory(): string
{
    $config = require __DIR__ . '/../../config/config.php';
    $configured = $config['inventory_evidence_path'] ?? null;
    if (!is_string($configured) || $configured === '' || !is_dir($configured)) {
        throw new RuntimeException('Configure inventory_evidence_path outside public folders.');
    }
    $directory = realpath($configured);
    if ($directory === false || !is_writable($directory)) {
        throw new RuntimeException('Private inventory evidence storage is unavailable.');
    }
    foreach ([realpath(__DIR__ . '/../..'),
        !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false] as $root) {
        if ($root && ($directory === $root ||
            str_starts_with(strtolower($directory . DIRECTORY_SEPARATOR),
                strtolower($root . DIRECTORY_SEPARATOR)))) {
            throw new RuntimeException('Private inventory evidence cannot live in public folders.');
        }
    }
    return $directory;
}

function stockEvidencePath(string $storedName): string
{
    if (!preg_match('/^[a-f0-9]{48}\.(jpg|png|pdf)$/D', $storedName)) {
        throw new DomainException('This evidence file is unavailable.');
    }
    return stockEvidenceDirectory() . DIRECTORY_SEPARATOR . $storedName;
}

/** Inspect actual file contents, never trust extension or browser MIME headers. */
function stockEvidenceInspect(string $path): array
{
    if (!is_file($path) || is_link($path) || !is_readable($path)) {
        throw new DomainException('The evidence file is unavailable.');
    }
    $bytes = filesize($path);
    if ($bytes === false || $bytes < 1 || $bytes > 5 * 1024 * 1024) {
        throw new DomainException('Evidence must be at most 5 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
        throw new DomainException('Only verified PNG/JPEG photos or PDF inspection documents are accepted.');
    }
    if ($mime === 'application/pdf') {
        $handle = fopen($path, 'rb');
        if (!$handle) throw new DomainException('Evidence is not readable.');
        try {
            $magic = fread($handle, 5);
        } finally {
            fclose($handle);
        }
        if ($magic !== '%PDF-') {
            throw new DomainException('The inspection PDF is not valid.');
        }
    } else {
        $image = @getimagesize($path);
        if (!$image || ($image['mime'] ?? '') !== $mime ||
            $image[0] < 1 || $image[1] < 1 || $image[0] > 6000 || $image[1] > 6000 ||
            $image[0] * $image[1] > 20000000) {
            throw new DomainException('Use a valid image up to 20 megapixels and 6000 pixels per side.');
        }
    }
    $sha = hash_file('sha256', $path);
    if (!is_string($sha)) throw new DomainException('The evidence checksum is unavailable.');
    return [
        'mime' => $mime,
        'extension' => ['image/jpeg'=>'jpg', 'image/png'=>'png', 'application/pdf'=>'pdf'][$mime],
        'bytes' => (int)$bytes,
        'sha256' => $sha,
    ];
}

function stockEvidenceCase(PDO $pdo, string $type, int $id): array
{
    if ($id <= 0 || !in_array($type, ['LOSS', 'RECOVERY'], true)) {
        throw new DomainException('This investigation case is unavailable.');
    }
    if ($type === 'LOSS') {
        $sql = 'SELECT loss.loss_case_id AS case_id, loss.status AS case_status,
            item.inventory_transfer_item_id AS item_id,
            transfer.inventory_transfer_id AS transfer_id,
            transfer.from_branch_id,transfer.to_branch_id
            FROM stock_transfer_loss_cases loss
            JOIN inventory_transfer_items item
                ON item.inventory_transfer_item_id=loss.inventory_transfer_item_id
            JOIN inventory_transfers transfer ON transfer.inventory_transfer_id=item.inventory_transfer_id
            WHERE loss.loss_case_id=?';
    } else {
        $sql = 'SELECT recovery.recovery_id AS case_id, recovery.status AS case_status,
            item.inventory_transfer_item_id AS item_id,
            transfer.inventory_transfer_id AS transfer_id,
            transfer.from_branch_id,transfer.to_branch_id
            FROM stock_transfer_recoveries recovery
            JOIN stock_transfer_loss_cases loss ON loss.loss_case_id=recovery.loss_case_id
            JOIN inventory_transfer_items item
                ON item.inventory_transfer_item_id=loss.inventory_transfer_item_id
            JOIN inventory_transfers transfer ON transfer.inventory_transfer_id=item.inventory_transfer_id
            WHERE recovery.recovery_id=?';
    }
    $case = stockRun($pdo,$sql,[$id])->fetch();
    if (!$case) throw new DomainException('This investigation case is unavailable.');
    return $case;
}

/** Both branches can inspect evidence; cashiers, technicians and other branches cannot. */
function stockEvidenceAuthorize(PDO $pdo, int $actor, array $case): void
{
    $branch=stockRun($pdo,"SELECT a.branch_id FROM user_branch_assignments a
        JOIN branches b ON b.branch_id=a.branch_id AND b.is_active=1
        JOIN users u ON u.user_id=a.user_id AND u.is_active=1
        WHERE a.user_id=? AND a.branch_id IN (?,?)
        AND u.role IN ('ADMIN','MANAGER') LIMIT 1",
        [$actor,(int)$case['from_branch_id'],(int)$case['to_branch_id']])->fetchColumn();
    if (!$branch) {
        throw new DomainException('The investigation evidence is unavailable to your account.');
    }
}

/**
 * Core persistence takes an already-staged and validated private file.
 * Upload entry point below checks is_uploaded_file and stages it first.
 * DB failures delete only the newly staged file; old evidence is never removed.
 */
function stockEvidenceSaveStaged(PDO $pdo, int $actor, string $type, int $caseId,
    string $stagedPath, string $originalName, array $details): int
{
    $name = basename(str_replace('\\','/',$originalName));
    if ($name === '' || mb_strlen($name) > 150 ||
        preg_match('/[\x00-\x1f\x7f]/', $name) ||
        !in_array($type,['LOSS','RECOVERY'],true)) {
        throw new DomainException('Choose a file with a safe name of up to 150 characters.');
    }
    $case=stockEvidenceCase($pdo,$type,$caseId);
    $pdo->beginTransaction();
    try {
        // Match the transfer lock order used by loss decisions.
        stockRun($pdo,'SELECT inventory_transfer_id FROM inventory_transfers
            WHERE inventory_transfer_id=? FOR UPDATE',[(int)$case['transfer_id']]);
        stockRun($pdo,'SELECT inventory_transfer_item_id FROM inventory_transfer_items
            WHERE inventory_transfer_item_id=? FOR UPDATE',[(int)$case['item_id']]);
        $table=$type==='LOSS'?'stock_transfer_loss_cases':'stock_transfer_recoveries';
        $pk=$type==='LOSS'?'loss_case_id':'recovery_id';
        $status=stockRun($pdo,"SELECT status FROM $table WHERE $pk=? FOR UPDATE",
            [$caseId])->fetchColumn();
        if ($status!=='PENDING') {
            throw new DomainException('Attach evidence before the investigation is independently decided.');
        }
        stockEvidenceAuthorize($pdo,$actor,$case);
        $column=$type==='LOSS'?'loss_case_id':'recovery_id';
        $count=(int)stockRun($pdo,"SELECT COUNT(*) FROM stock_transfer_evidence
            WHERE $column=?",[$caseId])->fetchColumn();
        if ($count>=6) {
            throw new DomainException('This investigation already contains six evidence files.');
        }
        $storedName=basename($stagedPath);
        stockRun($pdo,"INSERT INTO stock_transfer_evidence
            ($column,stored_name,original_filename,mime_type,file_bytes,sha256,
             uploaded_by_user_id,uploaded_at)
            VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP())",
            [$caseId,$storedName,$name,$details['mime'],$details['bytes'],$details['sha256'],$actor]);
        $evidenceId=(int)$pdo->lastInsertId();
        stockRun($pdo,"INSERT INTO audit_events
            (actor_type,actor_user_id,entity_type,entity_id,action,after_data,reason,created_at)
            VALUES ('USER',?,'inventory_transfer_item',?,'STOCK_EVIDENCE_ATTACHED',?,NULL,UTC_TIMESTAMP())",
            [$actor,(int)$case['item_id'],json_encode([
                'case_type'=>$type,'case_id'=>$caseId,'evidence_id'=>$evidenceId,
                'bytes'=>$details['bytes'],'sha256'=>$details['sha256']
            ],JSON_THROW_ON_ERROR)]);
        $pdo->commit();
        return $evidenceId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function stockEvidenceUpload(PDO $pdo, int $actor, string $type, int $caseId, array $file): int
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK ||
        !is_string($file['tmp_name'] ?? null) ||
        !is_uploaded_file($file['tmp_name'])) {
        throw new DomainException('Choose a JPEG, PNG or PDF evidence file up to 5 MB.');
    }
    $name = (string)($file['name'] ?? '');
    // Validate BEFORE moving and never rely on file['type'] or original extension.
    $details = stockEvidenceInspect($file['tmp_name']);
    $stored=bin2hex(random_bytes(24)).'.'.$details['extension'];
    $path=stockEvidencePath($stored);
    if (!move_uploaded_file($file['tmp_name'],$path)) {
        throw new RuntimeException('Evidence could not be stored privately.');
    }
    @chmod($path,0600);
    try {
        return stockEvidenceSaveStaged($pdo,$actor,$type,$caseId,$path,$name,$details);
    } catch (Throwable $error) {
        if (is_file($path) && !is_link($path)) @unlink($path);
        throw $error;
    }
}

function stockEvidenceList(PDO $pdo, int $actor, string $type, int $caseId): array
{
    $case=stockEvidenceCase($pdo,$type,$caseId);
    stockEvidenceAuthorize($pdo,$actor,$case);
    $column=$type==='LOSS'?'loss_case_id':'recovery_id';
    return stockRun($pdo,"SELECT evidence_id,original_filename,mime_type,file_bytes,uploaded_at
        FROM stock_transfer_evidence WHERE $column=?
        ORDER BY evidence_id DESC LIMIT 6",[$caseId])->fetchAll();
}

/** Re-authorize on EVERY download; a private file name is never a public URL. */
function stockEvidenceRead(PDO $pdo, int $actor, int $id): array
{
    $record=stockRun($pdo,'SELECT * FROM stock_transfer_evidence
        WHERE evidence_id=?',[$id])->fetch();
    if (!$record) throw new DomainException('This evidence file is unavailable.');
    $type=$record['loss_case_id']===null?'RECOVERY':'LOSS';
    $caseId=(int)($record['loss_case_id'] ?? $record['recovery_id']);
    stockEvidenceAuthorize($pdo,$actor,stockEvidenceCase($pdo,$type,$caseId));
    $path=stockEvidencePath((string)$record['stored_name']);
    if (is_link($path) || !is_file($path) || !is_readable($path)) {
        throw new DomainException('This evidence file is unavailable.');
    }
    $actual=stockEvidenceInspect($path);
    if ($actual['mime']!==$record['mime_type'] ||
        $actual['bytes']!==(int)$record['file_bytes'] ||
        !hash_equals((string)$record['sha256'],$actual['sha256'])) {
        throw new DomainException('This evidence file did not pass its original integrity check.');
    }
    return [
        'path'=>$path,'mime'=>$actual['mime'],'bytes'=>$actual['bytes'],
        'extension'=>$actual['extension'],'id'=>(int)$record['evidence_id']
    ];
}
