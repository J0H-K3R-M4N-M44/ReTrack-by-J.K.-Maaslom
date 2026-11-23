<?php
declare(strict_types=1);

/** Nager.Date Philippine holiday reference. This is a DEMO scheduling hint, not shop hours. */
function edpHolidayDecode(string $json): array
{
    if (strlen($json) > 262144) throw new DomainException('Holiday response is too large.');
    try { $rows = json_decode($json, true, 64, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new DomainException('Holiday service returned invalid JSON.', 0, $e); }
    if (!is_array($rows) || !array_is_list($rows) || count($rows) > 100) {
        throw new DomainException('Holiday service returned an unexpected list.');
    }
    $result=[];
    foreach ($rows as $record) {
        if (!is_array($record) || ($record['countryCode'] ?? null) !== 'PH' ||
            !is_string($record['date'] ?? null) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $record['date']) ||
            !is_string($record['name'] ?? null) ||
            ($record['name'] ?? '') === '' || strlen($record['name']) > 150 ||
            !is_bool($record['global'] ?? null)) {
            throw new DomainException('Holiday service provided unexpected fields.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $record['date'], new DateTimeZone('Asia/Manila'));
        if (!$date || $date->format('Y-m-d') !== $record['date']) {
            throw new DomainException('Holiday service provided an invalid date.');
        }
        // Regional holidays can be important but are not automatically applicable to Sogod.
        if (!$record['global']) continue;
        $types = $record['types'] ?? [];
        if (!is_array($types) || !in_array('Public', $types, true)) continue;
        $result[]=['date'=>$record['date'], 'name'=>trim($record['name'])];
    }
    usort($result, static fn(array $a, array $b): int => strcmp($a['date'], $b['date']));
    return $result;
}

/** Fixed HTTPS endpoint, no user-provided URLs or proxy redirects. */
function edpHolidayHttp(int $year): string
{
    $url = "https://date.nager.at/api/v3/PublicHolidays/{$year}/PH";
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl === false) throw new RuntimeException('Holiday service unavailable.');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5,
            CURLOPT_USERAGENT => 'RepairTrack-EDP-classroom/1',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        try {
            $body = curl_exec($curl);
            $code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if (!is_string($body) || $code !== 200) throw new RuntimeException('Holiday service unavailable.');
            return $body;
        } finally { curl_close($curl); }
    }
    $ctx=stream_context_create([
        'http'=>['timeout'=>5,'follow_location'=>0,'ignore_errors'=>false,
            'header'=>"Accept: application/json\r\nUser-Agent: RepairTrack-EDP-classroom/1\r\n"],
        'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true],
    ]);
    $body=@file_get_contents($url,false,$ctx,0,262145);
    if (!is_string($body)) throw new RuntimeException('Holiday service unavailable.');
    return $body;
}

/** Transport can be replaced with synthetic JSON during offline CI. */
function edpHolidayLookup(int $year, ?callable $transport = null): array
{
    $now = (int)(new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y');
    if ($year < $now || $year > $now + 1) throw new DomainException('Choose the current or next calendar year.');
    $rows = edpHolidayDecode(($transport ?? 'edpHolidayHttp')($year));
    return ['year'=>$year,'country'=>'PH','holidays'=>$rows,
        'source'=>'Nager.Date public holiday API','note'=>'Not a declaration of branch operating hours.'];
}
