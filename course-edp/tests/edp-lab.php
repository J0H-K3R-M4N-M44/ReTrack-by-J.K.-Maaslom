<?php
declare(strict_types=1);
/** Offline unit checks for the external REST/JSON adapter. No DB or network needed. */
if (PHP_SAPI !== 'cli') exit(2);
require_once __DIR__.'/../app/holiday-api.php';
function labCheck(bool $truth, string $message): void
{
    if (!$truth) throw new RuntimeException('FAIL: '.$message);
    echo 'PASS: '.$message."\n";
}
function labReject(callable $fn, string $message): void
{
    try { $fn(); } catch (DomainException $e) { labCheck(true,$message);return; }
    throw new RuntimeException('FAIL: '.$message);
}
$year=(int)(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y');
$good=json_encode([
    ['date'=>"$year-12-31",'name'=>'Public Holiday B','countryCode'=>'PH','global'=>true,'types'=>['Public']],
    ['date'=>"$year-01-01",'name'=>'Public Holiday A','countryCode'=>'PH','global'=>true,'types'=>['Public']],
    ['date'=>"$year-10-01",'name'=>'Regional Holiday','countryCode'=>'PH','global'=>false,'types'=>['Public']],
    ['date'=>"$year-05-01",'name'=>'Bank holiday','countryCode'=>'PH','global'=>true,'types'=>['Bank']],
],JSON_THROW_ON_ERROR);
$data=edpHolidayLookup($year, fn(int $requested): string=>$good);
labCheck($data['country']==='PH' && $data['year']===$year && count($data['holidays'])===2,
    'transport injection parses and filters national public holidays');
labCheck($data['holidays'][0]['name']==='Public Holiday A','holiday dates are sorted');
labReject(fn()=>edpHolidayDecode('garbled JSON'), 'invalid JSON rejected');
labReject(fn()=>edpHolidayDecode('{"message":"not a list"}'),'unexpected JSON object rejected');
labReject(fn()=>edpHolidayDecode(json_encode([['date'=>'2026-13-41','name'=>'Bad',
    'countryCode'=>'PH','global'=>true,'types'=>['Public']]])),'invalid calendar date rejected');
labReject(fn()=>edpHolidayDecode(json_encode([['date'=>"$year-12-31",'name'=>'Bad',
    'countryCode'=>'US','global'=>true,'types'=>['Public']]])),'other country data rejected');
labReject(fn()=>edpHolidayDecode(str_repeat('x',262145)),'oversized external JSON rejected');
labReject(fn()=>edpHolidayLookup($year-1,fn()=> $good),'past-year input rejected');
labReject(fn()=>edpHolidayLookup($year+2,fn()=> $good),'far-future year rejected');
labCheck(true,'external API tests require no live network or personal data');
