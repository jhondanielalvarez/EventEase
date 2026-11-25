<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/app/bootstrap.php';

function esc($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
ini_set('display_errors','0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
$depositPercent = 30.0; $resortSettings=[];
$databaseOnline = false;
$venues = [];
$packages = [];
$addons = [];
try {
    $pdo = eventease_pdo();
    $venues = $pdo->query("SELECT id, name, description, capacity, base_price FROM venues WHERE is_active=1 ORDER BY name")->fetchAll();
    $packages = $pdo->query("SELECT p.id,p.venue_id,p.name,p.description,p.package_price FROM packages p JOIN venues v ON v.id=p.venue_id WHERE p.is_active=1 AND v.is_active=1 ORDER BY p.venue_id,p.package_price")->fetchAll();
    $addons = $pdo->query("SELECT id, name, description, unit_price FROM addons WHERE is_active=1 ORDER BY name")->fetchAll();
    $depositPercent = eventease_deposit_percent($pdo); $resortSettings=$pdo->query("SELECT setting_key,setting_value FROM app_settings")->fetchAll(PDO::FETCH_KEY_PAIR); $databaseOnline = true;
} catch (Throwable $error) {
    // Never show database errors or connection credentials to website visitors.
    error_log('EventEase catalog unavailable: ' . $error->getMessage());
}
$demoMode = count($venues) === 0;
if ($databaseOnline && !$demoMode) {
    try {
        $demoMode = ($pdo->query("SELECT setting_value FROM app_settings WHERE setting_key='catalog_mode'")->fetchColumn() !== 'verified');
    } catch (Throwable $ignored) { $demoMode = true; }
}
if (count($venues) === 0) {
    // Fictional data for UI evaluation only. These are NOT verified resort offers.
    $venues = [
        ['id'=>'sample-garden','name'=>'Garden Pavilion','description'=>'An open-air concept for intimate celebrations and daytime gatherings.','capacity'=>100,'base_price'=>8500],
        ['id'=>'sample-pool','name'=>'Poolside Events Area','description'=>'A relaxed outdoor setting for birthdays, reunions and evening occasions.','capacity'=>80,'base_price'=>10000],
        ['id'=>'sample-hall','name'=>'Grand Function Hall','description'=>'A flexible indoor-style venue concept for larger milestone events.','capacity'=>180,'base_price'=>16000],
    ];
    $packages = [
        ['id'=>'sample-classic','venue_id'=>'sample-garden','name'=>'Classic Celebration','description'=>'Venue setup and basic tables and chairs (illustrative inclusion).','package_price'=>3500],
        ['id'=>'sample-sunset','venue_id'=>'sample-pool','name'=>'Sunset Gathering','description'=>'Event setup and simple lighting (illustrative inclusion).','package_price'=>5000],
        ['id'=>'sample-grand','venue_id'=>'sample-hall','name'=>'Grand Occasion','description'=>'Flexible event setup and basic sound equipment (illustrative inclusion).','package_price'=>7500],
    ];
    $addons = [
        ['id'=>'sample-decor','name'=>'Decorative Styling','description'=>'Illustrative add-on','unit_price'=>2200],
        ['id'=>'sample-sound','name'=>'Sound System','description'=>'Illustrative add-on','unit_price'=>1700],
        ['id'=>'sample-light','name'=>'Extra Event Lighting','description'=>'Illustrative add-on','unit_price'=>1200],
    ];
}
$clientData = ['venues'=>$venues, 'packages'=>$packages, 'addons'=>$addons, 'demoMode'=>$demoMode,'depositPercent'=>$depositPercent];
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>EventEase | Terraza Del Rio Private Resort &amp; Events Place</title>
<meta name="description" content="Explore event venues, packages and reservation planning with EventEase.">
<link rel="icon" type="image/jpeg" href="assets/images/resort/logo.jpg"><link rel="stylesheet" href="assets/css/site.css?v=final-1">
</head>
<body>
<header class="mobile-header">
  <a class="mobile-brand" href="#home"><img src="assets/images/resort/logo.jpg" alt="" width="38" height="38"><span>EventEase</span></a>
  <button class="menu-toggle sidebar-toggle" id="menu-toggle" type="button" aria-controls="customer-sidebar" aria-expanded="false" aria-label="Open navigation"><span aria-hidden="true">☰</span><span>Menu</span></button>
</header>
<div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>
<aside class="customer-sidebar" id="customer-sidebar" aria-label="Customer navigation">
  <a class="sidebar-brand" href="#home" aria-label="EventEase Home">
    <img src="assets/images/resort/logo.jpg" width="48" height="48" alt="Terraza Del Rio logo">
    <div><strong>EventEase</strong><small>Terraza Del Rio</small></div>
  </a>
  <div class="sidebar-intro">PRIVATE RESORT<br>&amp; EVENTS PLACE</div>
  <div class="sidebar-divider"></div>
  <nav id="main-nav" aria-label="Main navigation"><div class="nav-label">Explore</div><a class="sidebar-link is-active" href="#home" aria-current="location"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg><span>Home</span></a><a class="sidebar-link" href="#venues"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 10h18M9 4v17m6-11v11"/></svg><span>Venues</span></a><a class="sidebar-link" href="#packages"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="8" width="18" height="13" rx="1"/><path d="M2 8h20V5H2zM12 5v16M12 5C8 5 6 2 9 2c2 0 3 3 3 3s1-3 3-3c3 0 1 3-3 3z"/></svg><span>Packages</span></a><a class="sidebar-link" href="#gallery"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8" cy="8" r="1.5"/><path d="m3 17 6-6 4 4 3-3 5 5"/></svg><span>Gallery</span></a><div class="nav-label">Reservations</div><a class="sidebar-link" href="#calendar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18m-12 5h2m4 0h2m-8 3h2"/></svg><span>Availability</span></a><a href="#reserve" class="sidebar-reserve"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><span>Book Reservation</span></a><a class="sidebar-link" href="#track"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><rect x="4" y="4" width="16" height="17" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/></svg><span>Track Reservation</span></a><div class="nav-label">Support</div><a class="sidebar-link" href="#contact"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg><span>Contact</span></a></nav>
  <div class="sidebar-bottom"><span class="sidebar-dot"></span>Reservation portal</div>
</aside>
<div class="customer-content">
<?php if (isset($_GET['from']) && $_GET['from']==='admin'): ?>
<div class="admin-return" role="navigation" aria-label="Return to admin dashboard"><div class="container"><a href="admin/">← Return to Admin Dashboard</a><span>Customer website preview</span></div></div>
<?php endif; ?>

<main>
<section class="hero" id="home">
 <div class="container hero-grid">
  <div>
   <span class="eyebrow">Beautiful moments start here</span>
   <h1>A special place for <em>every occasion.</em></h1>
   <p class="lead">Explore event spaces, discover packages, and prepare your reservation in a simple, organized way.</p>
   <div class="hero-actions"><a href="#venues" class="btn btn-primary">Explore Venues <span aria-hidden="true">↗</span></a><a href="#calendar" class="btn btn-outline">View Calendar</a></div>
   <div class="home-quick"><a href="#calendar"><strong>Check availability</strong><small>Find an open schedule</small></a><a href="#track"><strong>Track reservation</strong><small>Check an existing booking</small></a></div>
  </div>
  <div class="visual-card resort-hero">
    <img src="assets/images/resort/pool-overview.jpg" alt="Terraza Del Rio swimming pool and surrounding resort facilities" width="1440" height="1080" fetchpriority="high" decoding="async">
    <div class="visual-tag"><strong>Terraza Del Rio</strong><span>Private Resort &amp; Events Place</span></div>
  </div>
 </div>
</section>
<section class="section-pad" id="venues">
 <div class="container">
  <div class="section-head"><div><span class="eyebrow">Find the perfect space</span><h2>Explore event venues</h2></div><p>Choose a setting that fits your event type and number of guests. Preview information will be updated with verified resort details.</p></div>
  <div class="card-grid">
   <?php foreach ($venues as $i => $v): ?>
    <article class="venue-card">
      <?php
      $venueName = strtolower((string)$v['name']);
      $venueImage = str_contains($venueName, 'pool') ? ['pool-day.jpg','Outdoor swimming pool'] :
        (str_contains($venueName, 'hall') ? ['function-hall.jpg','Indoor function hall with event tables'] :
        ((str_contains($venueName, 'garden') || str_contains($venueName, 'pavilion')) ? ['pavilion.jpg','Covered open-air pavilion'] : null));
      $selectedPhoto=$resortSettings['venue_photo_'.(int)$v['id']]??'';
      if(in_array($selectedPhoto,['pool-day.jpg','pool-overview.jpg','function-hall.jpg','pavilion.jpg','pool-night.jpg','reception-setup.jpg','outdoor-event.jpg'],true))$venueImage=[$selectedPhoto,$v['name'].' photo'];
      ?>
      <div class="venue-art<?= $venueImage ? ' resort-photo' : ' style-'.(int)($i % 3) ?>">
        <?php if ($venueImage): ?><img src="assets/images/resort/<?= esc($venueImage[0]) ?>" alt="<?= esc($venueImage[1]) ?>" width="900" height="675" loading="lazy" decoding="async"><?php endif; ?>
        <?php if ($demoMode): ?><span class="art-tag">Sample venue details</span><?php endif; ?>
      </div>
      <div class="venue-body">
        <h3><?= esc($v['name']) ?></h3>
        <p><?= esc($v['description'] ?: 'Event venue information and facilities.') ?></p>
        <div class="venue-meta"><div><small>Up to <?= (int)$v['capacity'] ?> guests</small><strong>From ₱<?= number_format((float)$v['base_price'], 2) ?></strong></div><a href="#reserve" class="mini-link venue-pick" data-venue="<?= esc($v['id']) ?>">Select venue ↗</a></div>
      </div>
    </article>
   <?php endforeach; ?>
  </div>
 </div>
</section>
<section class="section-pad packages-section" id="packages">
 <div class="container">
  <div class="section-head"><div><span class="eyebrow">Make it your own</span><h2>Packages &amp; inclusions</h2></div><p>Understand what each package offers before planning your event. Prices shown <?= $demoMode ? 'are sample values' : 'come from the venue catalog' ?>.</p></div>
  <div class="pkg-grid">
  <?php if (!$packages): ?><p>Package information will appear here once added by the resort.</p><?php endif; ?>
  <?php foreach ($packages as $p):
       $parent = null; foreach ($venues as $v) { if ((string)$v['id']===(string)$p['venue_id']) {$parent=$v;break;} }
  ?>
   <article class="pkg-card">
    <span class="pkg-venue"><?= esc($parent['name'] ?? 'Event package') ?></span>
    <h3><?= esc($p['name']) ?></h3>
    <p><?= esc($p['description'] ?: 'Detailed package inclusions will appear here.') ?></p>
    <div class="pkg-bottom"><div><span>Package add-on</span><br><strong>₱<?= number_format((float)$p['package_price'], 2) ?></strong></div><a href="#reserve" class="mini-link package-pick" data-venue="<?= esc($p['venue_id']) ?>" data-package="<?= esc($p['id']) ?>">Choose ↗</a></div>
   </article>
  <?php endforeach; ?>
  </div>
 </div>
</section>
<section class="section-pad" id="calendar">
 <div class="container dual">
  <div class="calendar-copy">
   <span class="eyebrow">Choose your event date</span>
   <h2 class="section-title">Plan around a date that works.</h2>
   <p>Browse the calendar and choose your preferred event date. Selecting a date fills it into the reservation preview below.</p>
   <div class="calendar-callout"><strong>Reservation availability</strong>Choose a venue and date to view reserved and temporarily held time slots. The server checks conflicts again before saving your request.</div>
   <div class="availability-tools"><div class="field"><label for="availability-venue">Venue</label><select id="availability-venue"><?php foreach($venues as $v): ?><option value="<?= esc($v['id']) ?>"><?= esc($v['name']) ?></option><?php endforeach; ?></select></div><div class="field"><label for="availability-date">Event date</label><input id="availability-date" type="date"></div><div class="calendar-callout" id="availability-slots" role="status">Select a venue and date to view booked hours.</div></div><a href="#reserve" id="availability-continue" class="btn btn-primary" style="align-self:flex-start;margin-top:22px">Continue to Reservation ↗</a>
  </div>
  <div class="calendar-panel">
   <div class="cal-top"><h3 id="calendar-title">Calendar</h3><div class="cal-actions"><button type="button" id="month-prev" aria-label="Previous month">‹</button><button type="button" id="month-next" aria-label="Next month">›</button></div></div>
   <div class="weekday-grid" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
   <div id="calendar-days" class="days-grid" aria-label="Event date picker"></div>
   <div class="cal-footer"><span><i class="dot"></i>Selected date</span><span id="date-summary">Select a date to continue</span></div>
  </div>
 </div>
</section>
<section class="section-pad form-section" id="reserve">
 <div class="container">
  <div class="section-head"><div><span class="eyebrow">Your event, your way</span><h2>Online reservation</h2></div><p>Choose your venue and schedule, customize your package, and review your reservation before submitting.</p></div>
  <div class="form-layout">
   <div class="form-panel">
     <p class="subtle">Complete the five steps below. Required fields are marked *.</p>
     <form id="reservation-form" novalidate>
      <div class="form-grid">
       <div class="field"><label for="customer-name">Full name *</label><input id="customer-name" type="text" name="customer_name" autocomplete="name" maxlength="150" placeholder="Your full name" required></div>
       <div class="field"><label for="customer-phone">Contact number *</label><input id="customer-phone" type="tel" name="phone" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" title="Enter an 11-digit Philippine mobile number starting with 09" required></div>
       <div class="field full"><label for="customer-email">Email address (optional)</label><input id="customer-email" type="email" name="email" autocomplete="email" placeholder="name@example.com"></div>
       <div class="field"><label for="event-type">Event type *</label><select id="event-type" required><option value="">Choose an event</option><option>Wedding</option><option>Birthday</option><option>Family Reunion</option><option>Corporate Gathering</option><option>Other Event</option></select></div>
       <div class="field"><label for="guests">Number of guests *</label><input id="guests" type="number" min="1" max="1000" step="1" placeholder="e.g. 50" required><small id="guest-capacity">Select a venue for its capacity.</small></div>
       <div class="field"><label for="venue-select">Preferred venue *</label><select id="venue-select" required><?php foreach($venues as $v): ?><option value="<?= esc($v['id']) ?>"><?= esc($v['name']) ?></option><?php endforeach; ?></select></div>
       <div class="field"><label for="package-select">Event package</label><select id="package-select"><option value="">Venue only</option></select></div>
       <div class="field"><label for="event-date">Event date *</label><input id="event-date" type="date" required></div>
       <div class="field"><label for="event-start">Start time *</label><input id="event-start" type="time" value="13:00" required></div>
       <div class="field"><label for="event-end">End time *</label><input id="event-end" type="time" value="18:00" required></div>
       <div class="field full"><label>Optional add-ons</label><div class="addon-list" id="addon-list"></div></div>
      </div>
      <div id="availability-result" class="form-note" role="status" aria-live="polite">Choose a venue and event date to check reserved or held time slots.</div>
      <div class="form-note">Submitting this form creates a <strong>Pending</strong> reservation request, not a confirmed booking. The <?= esc(rtrim(rtrim(number_format($depositPercent,2,'.',''),'0'),'.')) ?>% down payment <?= $demoMode ? 'is a demonstration policy until the resort confirms its actual terms.' : 'follows the configured reservation policy.' ?></div>
      <button type="submit" class="btn btn-primary form-submit">Submit Reservation Request <span aria-hidden="true">↗</span></button>
      <div id="form-feedback" class="feedback" role="status" aria-live="polite"></div>
     </form>
   </div>
   <aside class="summary-panel" aria-label="Cost summary">
    <span class="pill <?= $demoMode ? 'warning' : '' ?>"><?= $demoMode ? 'Sample estimates' : 'Price estimate' ?></span>
    <h3 style="margin-top:14px">Your cost breakdown</h3>
    <p class="subtle">Updates as you choose your venue, package, and extras.</p>
    <div class="summary-row"><span>Venue rate</span><strong id="cost-venue">₱0.00</strong></div>
    <div class="summary-row"><span>Package</span><strong id="cost-package">₱0.00</strong></div>
    <div class="summary-row"><span>Add-ons</span><strong id="cost-addons">₱0.00</strong></div>
    <div class="total-row"><span>Estimated total</span><strong id="cost-total">₱0.00</strong></div>
    <div class="summary-row"><span><?= $demoMode ? 'Sample down payment' : 'Down payment' ?> (<?= esc(rtrim(rtrim(number_format($depositPercent,2,'.',''),'0'),'.')) ?>%)</span><strong id="cost-deposit">₱0.00</strong></div>
    <div class="summary-row"><span>Estimated remaining balance</span><strong id="cost-balance">₱0.00</strong></div>
    <div class="summary-annotation">Wait for admin review before sending GCash down payment. Pay the remaining balance at the resort on the event day.</div>
   </aside>
  </div>
 </div>
</section>
<section class="section-pad gallery-section" id="gallery">
 <div class="container">
  <div class="section-head"><div><span class="eyebrow">A closer look</span><h2>Resort gallery</h2></div><p>Explore photos of the facilities and previous event setups at Terraza Del Rio.</p></div>
  <div class="resort-gallery">
   <figure><a href="assets/images/resort/pool-night.jpg" target="_blank" rel="noopener"><img src="assets/images/resort/pool-night.jpg" alt="Illuminated swimming pool at night" width="1000" height="750" loading="lazy" decoding="async"></a><figcaption>Pool at night</figcaption></figure>
   <figure><a href="assets/images/resort/reception-setup.jpg" target="_blank" rel="noopener"><img src="assets/images/resort/reception-setup.jpg" alt="Indoor reception with blue and white table settings" width="1000" height="750" loading="lazy" decoding="async"></a><figcaption>Reception setup</figcaption></figure>
   <figure><a href="assets/images/resort/outdoor-event.jpg" target="_blank" rel="noopener"><img src="assets/images/resort/outdoor-event.jpg" alt="Outdoor event platform and garden seating" width="1000" height="750" loading="lazy" decoding="async"></a><figcaption>Outdoor event space</figcaption></figure>
  </div>
  <p class="photo-credit">Photos supplied from Facebook for the resort website preview. Venue names, capacities and package prices remain subject to confirmation.</p>
 </div>
</section>
<section class="section-pad" id="track"><div class="container"><div class="section-head"><div><span class="eyebrow">Your reservation</span><h2>Track reservation</h2></div><p>Check booking approval, event details, and recorded payments using your booking reference and contact number.</p></div><div class="dual"><div class="form-panel"><h3>Find your booking</h3><form id="tracking-form"><div class="field"><label for="track-reference">Booking reference</label><input id="track-reference" required maxlength="20" placeholder="EE-261124-XXXXXXXXXX" autocomplete="off"></div><div class="field"><label for="track-phone">Contact number used to book</label><input id="track-phone" type="tel" pattern="09[0-9]{9}" maxlength="11" required inputmode="numeric" placeholder="09XXXXXXXXX"></div><button class="btn btn-primary" type="submit">Find Reservation</button></form></div><div class="summary-panel" id="tracking-result" role="status" aria-live="polite"><h3>Reservation status</h3><p>Enter your booking reference and contact number to view the details.</p></div></div></div></section>
<section class="section-pad contact-section" id="contact"><div class="container"><div class="section-head"><div><span class="eyebrow">Customer support</span><h2>Get in touch</h2></div><p>Contact Terraza Del Rio for venue inquiries, reservation assistance, and directions.</p></div>
<?php
$contactFb=$resortSettings['resort_facebook']??'';
$contactPhone=trim($resortSettings['resort_phone']??'');$contactPhone2=trim($resortSettings['resort_phone_alt']??'');
$contactEmail=trim($resortSettings['resort_email']??'');
$contactAddress=trim($resortSettings['resort_address']??'');
$contactMap=$resortSettings['resort_map']??'';
$contactHours=trim($resortSettings['resort_hours']??'');
$validContactUrl=static fn($u)=>is_string($u)&&filter_var($u,FILTER_VALIDATE_URL)&&parse_url($u,PHP_URL_SCHEME)==='https';
?>
<div class="contact-cards">
<?php if($validContactUrl($contactFb)):?><article class="contact-method"><span class="contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 21v-8h3l.5-4H14V7c0-1 .3-2 2-2h2V2h-3c-3 0-5 2-5 5v2H7v4h3v8z"/></svg></span><h3>Facebook</h3><p>Terraza Del Rio Private Resort &amp; Events Place</p><a class="contact-link" href="<?=esc($contactFb)?>" target="_blank" rel="noopener noreferrer">Visit Facebook page ↗</a></article><?php endif;?>
<?php if($contactPhone!==''||$contactPhone2!==''):?><article class="contact-method"><span class="contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M7 3H4a1 1 0 0 0-1 1c0 9 8 17 17 17a1 1 0 0 0 1-1v-3l-5-2-2 2a14 14 0 0 1-7-7l2-2z"/></svg></span><h3>Phone</h3><p>For booking inquiries and assistance.</p><?php foreach(array_unique(array_filter([$contactPhone,$contactPhone2])) as $number):?><div style="margin:5px 0"><a class="contact-link" href="tel:<?=esc(preg_replace('/[^0-9+]/','',$number))?>"><?=esc($number)?></a></div><?php endforeach;?></article><?php endif;?>
<?php if(filter_var($contactEmail,FILTER_VALIDATE_EMAIL)):?><article class="contact-method"><span class="contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg></span><h3>Email</h3><p>For inquiries and event arrangements.</p><a class="contact-link" href="mailto:<?=esc($contactEmail)?>"><?=esc($contactEmail)?></a></article><?php endif;?>
<?php if($contactAddress!==''||$validContactUrl($contactMap)):?><article class="contact-method"><span class="contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg></span><h3>Resort location</h3><?php if($contactAddress!==''):?><p><?=esc($contactAddress)?></p><?php endif;?><?php if($validContactUrl($contactMap)):?><a class="contact-link" href="<?=esc($contactMap)?>" target="_blank" rel="noopener noreferrer">View map ↗</a><?php endif;?></article><?php endif;?>
<?php if($contactHours!==''):?><article class="contact-method"><span class="contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/></svg></span><h3>Operating hours</h3><p><?=esc($contactHours)?></p></article><?php endif;?>
</div>
<?php if(!$validContactUrl($contactFb)&&$contactPhone===''&&$contactPhone2===''&&!filter_var($contactEmail,FILTER_VALIDATE_EMAIL)):?><p>Contact details will be available once confirmed by the resort.</p><?php endif;?>
<div class="contact-help"><div><strong>Already have a reservation?</strong><p>Check your booking status and payment instructions directly in EventEase.</p></div><a href="#track" class="btn btn-primary">Track Reservation</a></div>
</div></section>
</main>
</div>
<script src="assets/js/availability.js?v=1"></script>
<script>
(function(){
 'use strict';
 var STORE = <?= json_encode($clientData, $jsonFlags) ?>;
 var venueEl=document.getElementById('venue-select'),packageEl=document.getElementById('package-select'),dateEl=document.getElementById('event-date');
 var guestsEl=document.getElementById('guests'), addonArea=document.getElementById('addon-list');
 var feedback=document.getElementById('form-feedback');
 var money=function(n){return new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP'}).format(Number(n)||0);};
 function chosenVenue(){return STORE.venues.find(function(v){return String(v.id)===venueEl.value;});}
 function chosenPackage(){return STORE.packages.find(function(p){return String(p.id)===packageEl.value && String(p.venue_id)===venueEl.value;});}
 function updatePackages(selectedId){
    var list=STORE.packages.filter(function(p){return String(p.venue_id)===venueEl.value;});
    packageEl.replaceChildren();
    var empty=document.createElement('option');empty.value='';empty.textContent='Venue only';packageEl.appendChild(empty);
    list.forEach(function(p){var opt=document.createElement('option');opt.value=p.id;opt.textContent=p.name+' (+ '+money(p.package_price)+')';packageEl.appendChild(opt);});
    if(selectedId && list.some(function(p){return String(p.id)===String(selectedId);})){packageEl.value=String(selectedId);}
    updateCosts();
 }
 function updateCosts(){
    var v=chosenVenue(),p=chosenPackage();var priceV=v?Number(v.base_price):0,priceP=p?Number(p.package_price):0;
    var addonsTotal=0;
    addonArea.querySelectorAll('input:checked').forEach(function(input){var item=STORE.addons.find(function(a){return String(a.id)===input.value;});if(item){addonsTotal+=Number(item.unit_price);}});
    var total=Math.round((priceV+priceP+addonsTotal)*100)/100;
    document.getElementById('cost-venue').textContent=money(priceV);
    document.getElementById('cost-package').textContent=money(priceP);
    document.getElementById('cost-addons').textContent=money(addonsTotal);
    document.getElementById('cost-total').textContent=money(total);
    var deposit=Math.round(Math.round(total*100)*Number(STORE.depositPercent)/100)/100; document.getElementById('cost-deposit').textContent=money(deposit);
    document.getElementById('cost-balance').textContent=money(total-deposit);
    if(v){guestsEl.max=Number(v.capacity);document.getElementById('guest-capacity').textContent='Venue limit: '+v.capacity+' guests';}
    return total;
 }
 STORE.addons.forEach(function(item){
    var row=document.createElement('div');row.className='addon-item';
    var label=document.createElement('label');var input=document.createElement('input');input.type='checkbox';input.value=item.id;input.addEventListener('change',updateCosts);
    var name=document.createElement('span');name.textContent=item.name;
    label.appendChild(input);label.appendChild(name);
    var price=document.createElement('span');price.textContent='+'+money(item.unit_price);
    row.appendChild(label);row.appendChild(price);addonArea.appendChild(row);
 });
 if(!STORE.addons.length){addonArea.textContent='Add-ons will be available when added to the catalog.';}
 venueEl.addEventListener('change',function(){updatePackages('');});
 packageEl.addEventListener('change',updateCosts);
 updatePackages('');

 document.querySelectorAll('.venue-pick').forEach(function(link){link.addEventListener('click',function(){venueEl.value=link.dataset.venue;updatePackages('');});});
 document.querySelectorAll('.package-pick').forEach(function(link){link.addEventListener('click',function(){venueEl.value=link.dataset.venue;updatePackages(link.dataset.package);});});

 function ymd(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}
 var now=new Date();now.setHours(0,0,0,0);dateEl.min=ymd(now);var maxDate=new Date(now);maxDate.setFullYear(maxDate.getFullYear()+2);dateEl.max=ymd(maxDate);
 var currentMonth=new Date(now.getFullYear(),now.getMonth(),1);
 var selectedDate='';
 var calDays=document.getElementById('calendar-days');
 function drawCalendar(){
   var year=currentMonth.getFullYear(),month=currentMonth.getMonth(),days=new Date(year,month+1,0).getDate();
   document.getElementById('calendar-title').textContent=currentMonth.toLocaleDateString('en-PH',{month:'long',year:'numeric'});
   document.getElementById('month-prev').disabled=year===now.getFullYear()&&month===now.getMonth();document.getElementById('month-next').disabled=year===maxDate.getFullYear()&&month===maxDate.getMonth();
   calDays.replaceChildren();
   var offset=new Date(year,month,1).getDay();
   for(var i=0;i<offset;i++){var spacer=document.createElement('span');spacer.className='day is-out';spacer.setAttribute('aria-hidden','true');calDays.appendChild(spacer);}
   for(var day=1;day<=days;day++){
      var d=new Date(year,month,day),value=ymd(d),btn=document.createElement('button');
      btn.type='button';btn.className='day';btn.textContent=day;btn.setAttribute('aria-label',value);
      if(d<now||d>maxDate){btn.disabled=true;btn.title=d<now?'Past date':'Outside booking period';}
      if(value===ymd(now)){btn.classList.add('is-today');}
      if(value===selectedDate){btn.classList.add('is-selected');btn.setAttribute('aria-pressed','true');}
      else{btn.setAttribute('aria-pressed','false');}
      btn.addEventListener('click',function(){selectedDate=this.getAttribute('aria-label');dateEl.value=selectedDate;document.getElementById('date-summary').textContent=new Date(selectedDate+'T12:00:00').toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});drawCalendar();});
      calDays.appendChild(btn);
   }
 }
 document.getElementById('month-prev').addEventListener('click',function(){currentMonth=new Date(currentMonth.getFullYear(),currentMonth.getMonth()-1,1);drawCalendar();});
 document.getElementById('month-next').addEventListener('click',function(){currentMonth=new Date(currentMonth.getFullYear(),currentMonth.getMonth()+1,1);drawCalendar();});
 dateEl.addEventListener('change',function(){
   selectedDate=dateEl.value;
   if(selectedDate){var d=new Date(selectedDate+'T12:00:00');if(!isNaN(d.getTime())){currentMonth=new Date(d.getFullYear(),d.getMonth(),1);document.getElementById('date-summary').textContent=d.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});}}
   drawCalendar();
 });
 drawCalendar();

 var availabilityText=document.getElementById('availability-result');
 var lastBookings=[],availabilitySeq=0;
 var apiUrl='reservations.php',form=document.getElementById('reservation-form'),submitting=false,submitted=false;
 async function checkAvailability(){
   var venue=venueEl.value,date=dateEl.value; var seq=++availabilitySeq; lastBookings=[];
   if(!venue||!date){availabilityText.textContent='Choose a venue and date to check reserved or held time slots.';lastBookings=[];return;}

   availabilityText.textContent='Checking reserved and held time slots for '+date+'...';
   try {
     var result=await window.EventEaseAvailability.get(venue,date);
     if(seq!==availabilitySeq)return;
     if(!result.ok)throw new Error(result.message||'Could not check schedule.');
     lastBookings=result.booked||[];
     if(!lastBookings.length){
       availabilityText.textContent='No reserved or held time slots appear for this date. Availability is checked again when you submit.';
     }else{
       var start=document.getElementById('event-start').value,end=document.getElementById('event-end').value;
       var overlap=lastBookings.some(function(x){return x.start_time<end && x.end_time>start;});
       availabilityText.textContent='Reserved or held times: '+lastBookings.map(function(x){return x.start_time+' – '+x.end_time;}).join(', ')+'. '+(overlap?'Your selected hours overlap; please choose another time.':'Your selected hours do not overlap.');
     }
   }catch(err){if(seq===availabilitySeq){availabilityText.textContent=err.message||'Schedule lookup failed.';lastBookings=[];}}
 }
 venueEl.addEventListener('change',checkAvailability);
 
 dateEl.addEventListener('change',checkAvailability);
 document.getElementById('event-start').addEventListener('change',checkAvailability);
 document.getElementById('event-end').addEventListener('change',checkAvailability);
 document.querySelectorAll('.venue-pick,.package-pick').forEach(function(a){a.addEventListener('click',function(){setTimeout(checkAvailability,0);});});
 document.getElementById('calendar-days').addEventListener('click',function(e){if(e.target.closest('button'))setTimeout(checkAvailability,0);});
 form.addEventListener('submit',async function(e){
   e.preventDefault();if(submitting||submitted)return;feedback.className='feedback';feedback.textContent='';
   if(!form.reportValidity())return;
   var v=chosenVenue(),start=document.getElementById('event-start').value,end=document.getElementById('event-end').value;
   if(!v){feedback.textContent='Choose a venue.';feedback.className='feedback is-visible is-error';return;}
   if(Number(guestsEl.value)>Number(v.capacity)){feedback.textContent='Guest count exceeds venue capacity.';feedback.className='feedback is-visible is-error';return;}
   if(start>=end){feedback.textContent='End time must be later than start time.';feedback.className='feedback is-visible is-error';return;}
   if(lastBookings.some(function(x){return x.start_time<end&&x.end_time>start;})){
      feedback.textContent='Selected time overlaps a reservation or temporary hold. Choose another time.';feedback.className='feedback is-visible is-error';return;
   }
   var submit=form.querySelector('[type=submit]');
   submitting=true;submit.disabled=true;submit.textContent='Submitting request...';
   try {
     var tokenResponse=await fetch(apiUrl+'?action=csrf',{cache:'no-store',credentials:'same-origin'});
     var token=await tokenResponse.json();
     if(!tokenResponse.ok||!token.ok)throw new Error('Secure session unavailable. Please try again.');
     var data={
       csrf_token:token.csrf_token,
       name:document.getElementById('customer-name').value.trim(),
       phone:document.getElementById('customer-phone').value.trim(),
       email:document.getElementById('customer-email').value.trim(),
       event_type:document.getElementById('event-type').value,
       guests:Number(guestsEl.value),venue_id:venueEl.value,
       package_id:packageEl.value||null,date:dateEl.value,start:start,end:end,
       addons:Array.from(addonArea.querySelectorAll('input:checked')).map(function(x){return x.value;})
     };
     var response=await fetch(apiUrl+'?action=submit',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify(data)});
     var result=await response.json();
     if(!response.ok||!result.ok)throw new Error(result.message||'Unable to save request.');
     feedback.textContent='Reservation request saved! Reference: '+result.reference+'. Status: Pending. Estimated total: '+money(result.total)+'. Save your reference for tracking. Wait for the resort to review your schedule before sending any GCash payment.';
     feedback.className='feedback is-visible';
     submitted=true;submit.textContent='Request Submitted';window.EventEaseAvailability.clear();
     await checkAvailability();
   }catch(err){
     feedback.textContent=err.message||'Could not submit your reservation.';
     feedback.className='feedback is-visible is-error';
     submitting=false;submit.disabled=false;submit.textContent='Submit Reservation Request';
     await checkAvailability();
   }
   feedback.scrollIntoView({behavior:'smooth',block:'center'});
 });
})();
</script>
<script src="assets/js/sidebar.js?v=2" defer></script><script src="assets/js/journey.js?v=final-1" defer></script></body>
</html>
