<?php
// Included only after the existing Admin Dashboard authenticates the administrator.
if (!isset($staff,$db) || ($staff['role']??null)!=='admin') { http_response_code(403); exit; }
require_once dirname(__DIR__).'/app/report_data.php';
$month=reportMonth($_GET['month']??null);
try {
    $report=monthlyReport($db,$month);
} catch(Throwable $ex) {
    error_log('EventEase report: '.$ex->getMessage());
    http_response_code(503);
    exit('Monthly report is temporarily unavailable. Please return to the dashboard and try again.');
}
$monthLabel=(new DateTimeImmutable($report['from']))->format('F Y');
$paymentNames=['cash'=>'Cash','bank_transfer'=>'Bank transfer','e_wallet'=>'E-wallet','other'=>'Other'];
$maxType=max(array_merge([1],array_map(static fn($x)=>(int)$x['n'],$report['eventTypes'])));
$scheduled=array_sum($report['status']);
$accepted=$report['status']['confirmed']+$report['status']['paid']+$report['status']['completed'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>EventEase | Monthly Reports</title>
<link rel="icon" type="image/jpeg" href="../assets/images/resort/logo.jpg">
<style>
:root{--green:#173e34;--ink:#253c32;--muted:#65786d;--line:#dce5dc;--bg:#f5f7f3}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.6 system-ui,sans-serif}a{color:var(--green);text-decoration:none}
h1,h2{font:400 30px Georgia,serif;color:var(--green);margin:0}h2{font-size:23px;margin-bottom:8px}h3{font-size:15px;margin:0 0 10px}p{margin:0}
.layout{display:grid;grid-template-columns:220px minmax(0,1fr);min-height:100vh}aside{position:sticky;top:0;height:100vh;background:var(--green);padding:26px 19px;color:white}
.logo{display:flex;gap:10px;align-items:center;color:white;font-size:23px;font-weight:800;margin-bottom:36px}.logo img{border-radius:7px;object-fit:contain}
nav{display:grid;gap:4px}nav a{color:#d6e4d9;padding:10px 13px;border-radius:8px}nav a:hover,nav a.active{background:#ffffff20;color:white}
.me{margin-top:44px;font-size:12px;color:#c8ded0}.me strong{color:white;display:block}.me form{margin-top:12px}
main{padding:32px clamp(16px,3vw,45px);min-width:0;max-width:1500px;width:100%;margin:auto}.heading{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:20px}
.muted{color:var(--muted);font-size:13px}.eyebrow{font-size:11px;font-weight:750;letter-spacing:.13em;color:var(--muted)}
.btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 15px;min-height:40px;background:var(--green);color:white;border:1px solid var(--green);border-radius:8px;font:inherit;font-weight:700;cursor:pointer}.btn.alt{color:var(--green);background:white;border-color:#cbd9cf}
.filters{display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-bottom:22px;padding:16px;border:1px solid var(--line);background:white;border-radius:12px}
.filters label{display:grid;gap:5px;font-weight:700;font-size:12px}.filters input{min-height:40px;border:1px solid #cbd9cf;border-radius:8px;padding:8px 10px;color:var(--ink);font:inherit}
.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:22px}.metric,.panel{background:white;border:1px solid var(--line);border-radius:12px;padding:20px;min-width:0}.metric span{display:block;color:var(--muted);font-size:12px;font-weight:700}.metric strong{display:block;font:400 clamp(20px,2.2vw,29px) Georgia,serif;color:var(--green);overflow-wrap:anywhere;margin:10px 0 7px}.metric small{display:block;color:var(--muted);font-size:11px}
.two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:20px}.panel{margin-bottom:20px}.two .panel{margin:0}
.sub{color:var(--muted);font-size:12px;margin-bottom:14px}.row{display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid #edf1eb}.row:last-child{border:0}.row span:first-child{overflow-wrap:anywhere}
.bar-row{margin:12px 0}.track{height:7px;border-radius:10px;background:#e7eee7;margin-top:5px;overflow:hidden}.fill{height:100%;background:#51846c;border-radius:10px}.bar-label{display:flex;justify-content:space-between;gap:8px;font-size:13px}
.scroll{overflow-x:auto}table{border-collapse:collapse;width:100%;min-width:650px;font-size:12px;text-align:left}th,td{padding:10px 11px;border-bottom:1px solid #edf1eb;vertical-align:top}th{background:#f7f9f6;color:var(--muted);font-weight:750}td:last-child,th:last-child{text-align:right}.status{text-transform:capitalize;font-weight:700}.empty{color:var(--muted);padding:16px 0}
.note{font-size:12px;color:var(--muted);margin:12px 0 18px}.print-only{display:none}.footer{font-size:12px;color:var(--muted);padding:6px 0}.menu{display:none}
@media(max-width:1050px){.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:780px){.layout{display:block}aside{position:relative;height:auto;padding:14px 20px}.logo{margin:0}.menu{display:block;position:absolute;right:20px;top:15px;background:transparent;border:1px solid #ffffff80;color:white;border-radius:6px;padding:7px 12px}nav,.me{display:none}aside.open nav,aside.open .me{display:grid;margin-top:15px}main{padding:22px 14px}.two{grid-template-columns:1fr}.heading{align-items:flex-start}.metric,.panel{padding:15px}}
@media(max-width:480px){.heading{display:block}.heading .btn{margin-top:12px}.filters label{flex:1}.filters input{width:100%}.metrics{gap:8px}.metric{padding:13px}.metric strong{font-size:20px}}
@page{size:auto;margin:13mm}
@media print{body{background:white;font-size:10pt}.layout{display:block}aside,.no-print{display:none!important}main{padding:0;max-width:none}.heading{display:block}.print-only{display:block}.metrics{grid-template-columns:repeat(2,minmax(0,1fr));gap:6px}.metric,.panel{padding:10px;border-radius:0;box-shadow:none}.metric,.two .panel{break-inside:avoid}.two{grid-template-columns:1fr;gap:8px}.scroll{overflow:visible}table{min-width:0;font-size:9pt}th,td{padding:6px 4px}tr{break-inside:avoid}thead{display:table-header-group}h2{break-after:avoid}.track,.fill{print-color-adjust:exact;-webkit-print-color-adjust:exact}.footer{margin-top:12px}}
/* Fixed navigation, independently scrollable content */
@media screen and (min-width:801px){body{overflow:hidden}.layout{height:100dvh;min-height:0;overflow:hidden}.layout>aside{height:100dvh;min-height:0;overflow-y:auto}.layout>main{height:100dvh;min-height:0;overflow-y:auto;overscroll-behavior:contain;margin:0 auto}}

</style></head><body><div class="layout">
<aside id="report-sidebar"><a class="logo" href="index.php"><img src="../assets/images/resort/logo.jpg" alt="Terraza Del Rio logo" width="42" height="42">EventEase</a><button class="menu" id="report-menu" aria-controls="report-nav" aria-expanded="false" type="button">Menu</button>
<nav id="report-nav"><a href="index.php#overview">Overview</a><a href="index.php#reservations">Reservations</a><a href="index.php#payments-records">Payment Records</a><a href="?page=reports" class="active" aria-current="page">Reports &amp; Records</a><a href="?page=catalog">Resort Management</a><a href="index.php#account">Admin Account</a><a href="../?from=admin" target="_blank" rel="noopener noreferrer">Customer Website ↗</a></nav>
<div class="me"><strong><?=e($staff['full_name'])?></strong>Admin account<form method="post" action="index.php"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="logout"><button class="btn alt" type="submit">Sign out</button></form></div></aside>
<main>
<div class="heading"><div><div class="eyebrow">TERRAZA DEL RIO · ADMIN PORTAL</div><h1>Reports &amp; Records</h1><p class="muted">Monthly summary · <?=e($monthLabel)?></p></div><button class="btn no-print" type="button" onclick="window.print()">Print Report</button></div>
<p class="print-only muted">Terraza Del Rio Private Resort &amp; Events Place · Generated <?=e(date('M j, Y g:i A'))?> (Philippine time)</p>
<form class="filters no-print" method="get" action="index.php"><input type="hidden" name="page" value="reports"><label for="report-month">Report month<input type="month" id="report-month" name="month" min="2000-01" max="2099-12" value="<?=e($month)?>" required></label><button class="btn" type="submit">View Report</button><a class="btn alt" href="?page=reports">Current Month</a><a class="btn alt" href="index.php">Back to Dashboard</a></form>
<div class="metrics">
<div class="metric"><span>Scheduled reservations</span><strong><?=$scheduled?></strong><small>All statuses, by event date</small></div>
<div class="metric"><span>New requests received</span><strong><?=$report['newRequests']?></strong><small>By request creation date</small></div>
<div class="metric"><span>Received payments</span><strong><?=price($report['received'])?></strong><small>By actual payment received date</small></div>
<div class="metric"><span>Accepted booking value</span><strong><?=price($report['booked'])?></strong><small>Confirmed, paid and completed events</small></div>
</div>
<p class="note">Payments are manually recorded collections, not online transactions or net profit. Cancelled booking payments remain in the collection history; refunds are not tracked separately. Booking value is not cash received.</p>
<div class="two">
<section class="panel"><h2>Reservation status</h2><p class="sub">Events scheduled in <?=e($monthLabel)?>, current status</p><?php foreach($report['status'] as $st=>$n):?><div class="row"><span><?=e(ucfirst($st))?></span><strong><?=$n?></strong></div><?php endforeach;?><div class="row"><strong>Total scheduled</strong><strong><?=$scheduled?></strong></div></section>
<section class="panel"><h2>Popular event types</h2><p class="sub">Based on <?=$accepted?> accepted bookings scheduled this month.</p><?php if(!$report['eventTypes']):?><p class="empty">No accepted events for this month.</p><?php else:foreach($report['eventTypes'] as $type):?><div class="bar-row"><div class="bar-label"><span><?=e($type['event_type'])?></span><strong><?=(int)$type['n']?></strong></div><div class="track"><div class="fill" style="width:<?=round((int)$type['n']*100/$maxType,2)?>%"></div></div></div><?php endforeach;endif;?></section>
</div>
<div class="two">
<section class="panel"><h2>Collections by method</h2><p class="sub">Payments received during the selected month.</p><?php if(!$report['payments']):?><p class="empty">No payments recorded for this month.</p><?php else:foreach($report['payments'] as $p):?><div class="row"><span><?=e($paymentNames[$p['method']]??$p['method'])?> (<?=(int)$p['n']?>)</span><strong><?=price($p['amount'])?></strong></div><?php endforeach;endif;?><div class="row"><strong>Total received</strong><strong><?=price($report['received'])?></strong></div></section>
<section class="panel"><h2>Venue summary</h2><p class="sub">Confirmed, paid and completed events by venue.</p><?php if(!$report['venues']):?><p class="empty">No accepted venue bookings for this month.</p><?php else:foreach($report['venues'] as $v):?><div class="row"><span><?=e($v['name'])?></span><strong><?=(int)$v['n']?></strong></div><?php endforeach;endif;?></section>
</div>
<section class="panel"><h2>Monthly reservation records</h2><p class="sub">All <?=$scheduled?> scheduled reservations for <?=e($monthLabel)?>. Customer contact details omitted from this report.</p><div class="scroll"><table><thead><tr><th>Reference</th><th>Event</th><th>Venue</th><th>Date &amp; time</th><th>Status</th><th>Booking total</th></tr></thead><tbody><?php if(!$report['events']):?><tr><td colspan="6">No reservations scheduled for this month.</td></tr><?php endif;foreach($report['events'] as $r):?><tr><td><?=e($r['reference_code'])?></td><td><?=e($r['event_type'])?></td><td><?=e($r['venue_name'])?></td><td><?=e(date('M j, g:i A',strtotime($r['start_at'])))?> – <?=e(date('g:i A',strtotime($r['end_at'])))?></td><td class="status"><?=e($r['status'])?></td><td><?=price($r['total_amount'])?></td></tr><?php endforeach;?></tbody></table></div></section>
<section class="panel"><h2>Monthly payment records</h2><p class="sub">Payments received in <?=e($monthLabel)?>, including those for events scheduled in other months.</p><div class="scroll"><table><thead><tr><th>Reservation</th><th>Date received</th><th>Method</th><th>Amount</th></tr></thead><tbody><?php if(!$report['receipts']):?><tr><td colspan="4">No payments recorded for this month.</td></tr><?php endif;foreach($report['receipts'] as $r):?><tr><td><?=e($r['reference_code'])?></td><td><?=e(date('M j, Y g:i A',strtotime($r['received_at'])))?></td><td><?=e($paymentNames[$r['method']]??$r['method'])?></td><td><?=price($r['amount'])?></td></tr><?php endforeach;?></tbody></table></div></section>
<div class="footer">EventEase · Admin-only monthly report · All dates use Philippine time.</div>
</main></div><script>document.getElementById('report-menu').addEventListener('click',function(){var open=document.getElementById('report-sidebar').classList.toggle('open');this.setAttribute('aria-expanded',String(open));});</script></body></html>
