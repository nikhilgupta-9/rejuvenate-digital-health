<?php
/**
 * util/doctor-plans-render.php — shared themed pricing cards for doctor
 * membership plans (`doctor_plans`).
 *
 * Used by doctor-network.php and doctor-plans.php.
 *
 *   require_once __DIR__ . '/util/doctor-plans-render.php';
 *   render_doctor_plan_cards($plansArray, [
 *       'cta_mode'   => 'link' | 'subscribe',   // default 'link'
 *       'signup_url' => BASE_URL . 'doctor-signup.php',
 *       'compact'    => false,
 *   ]);
 */

if (!function_exists('plan_duration_text')) {
    /** "1 month" / "3 months" / "6 months" / "12 months" / "45 days" */
    function plan_duration_text(int $days): string
    {
        $known = [30 => '1 month', 60 => '2 months', 90 => '3 months', 180 => '6 months',
                  365 => '12 months', 366 => '12 months', 730 => '24 months'];
        if (isset($known[$days])) return $known[$days];
        if ($days % 30 === 0)     return ($days / 30) . ' months';
        return $days . ' days';
    }
}
if (!function_exists('plan_cycle_label')) {
    function plan_cycle_label(int $days): string { return '/' . plan_duration_text($days); }
}

if (!function_exists('render_doctor_plan_cards')) {

function _dpc_e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES); }

function render_doctor_plan_cards(array $plans, array $opts = []): void
{
    static $cssDone = false;

    $base      = defined('BASE_URL') ? BASE_URL : '/';
    $ctaMode   = $opts['cta_mode']   ?? 'link';
    $signupUrl = $opts['signup_url'] ?? ($base . 'doctor-signup.php');
    $compact   = !empty($opts['compact']);

    if (!$plans) {
        echo '<p class="text-muted text-center">No membership plans are available right now. Please check back soon.</p>';
        return;
    }

    if (!$cssDone) {
        $cssDone = true;
        ?>
<style>
.dpc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:22px;align-items:stretch;}
.dpc-card{position:relative;background:#fff;border:1.5px solid #e5e7eb;border-radius:16px;padding:26px 22px;
  display:flex;flex-direction:column;transition:.18s;}
.dpc-card:hover{border-color:#0C74C5;box-shadow:0 12px 30px rgba(12,116,197,.12);transform:translateY(-3px);}
.dpc-card.dpc-hot{border-color:#0C74C5;box-shadow:0 10px 26px rgba(12,116,197,.14);}
.dpc-ribbon{position:absolute;top:14px;right:-2px;background:#0C74C5;color:#fff;font-size:.66rem;font-weight:700;
  letter-spacing:.5px;text-transform:uppercase;padding:4px 12px;border-radius:20px 0 0 20px;}
.dpc-name{font-size:1.08rem;font-weight:800;color:#1f2937;margin:0;}
.dpc-tag{font-size:.82rem;color:#6b7280;margin:4px 0 14px;min-height:1.2em;}
.dpc-price{font-size:2rem;font-weight:800;color:#0C74C5;line-height:1;}
.dpc-dur{display:inline-block;background:#eaf4fd;color:#0C74C5;font-size:.74rem;font-weight:700;
  border-radius:20px;padding:3px 12px;margin-top:8px;text-transform:uppercase;letter-spacing:.4px;}
.dpc-permonth{display:block;font-size:.74rem;color:#9ca3af;margin-top:6px;}
.dpc-feats{list-style:none;padding:0;margin:16px 0 18px;flex:1;}
.dpc-feats li{position:relative;padding:5px 0 5px 24px;font-size:.84rem;color:#374151;}
.dpc-feats li i{position:absolute;left:0;top:7px;color:#02c9b8;font-size:.8rem;}
.dpc-cta{display:block;text-align:center;background:#0C74C5;color:#fff;border:none;border-radius:10px;
  padding:11px 16px;font-weight:700;font-size:.9rem;text-decoration:none;cursor:pointer;width:100%;}
.dpc-cta:hover{background:#0a5fa0;color:#fff;}
.dpc-card.dpc-compact{padding:20px 18px;}
.dpc-msg{font-size:.8rem;margin-top:8px;text-align:center;}
.dpc-settlement-note{display:flex;align-items:flex-start;gap:10px;background:#f0fdf4;border:1px solid #86efac;
  border-radius:12px;padding:14px 18px;margin-top:22px;font-size:.83rem;color:#166534;}
.dpc-settlement-note i{font-size:1.1rem;margin-top:1px;flex-shrink:0;}
</style>
        <?php
    }
    ?>
<div class="dpc-grid">
  <?php foreach ($plans as $p):
      $days     = (int)($p['billing_cycle_days'] ?? 30);
      $price    = (float)$p['price'];
      $months   = $days / 30;
      $perMonth = $months >= 1 ? round($price / $months) : null;
      $feats    = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($p['features'] ?? '')))));
  ?>
  <div class="dpc-card<?= !empty($p['is_highlighted']) ? ' dpc-hot' : '' ?><?= $compact ? ' dpc-compact' : '' ?>">
    <?php if (!empty($p['is_highlighted'])): ?><span class="dpc-ribbon">Most Popular</span><?php endif; ?>
    <h3 class="dpc-name"><?= _dpc_e($p['name']) ?></h3>
    <div class="dpc-tag"><?= _dpc_e($p['tagline'] ?? '') ?></div>

    <div class="dpc-price">&#8377;<?= number_format($price) ?></div>
    <span class="dpc-dur"><i class="fas fa-calendar-alt me-1"></i><?= _dpc_e(plan_duration_text($days)) ?> plan</span>
    <?php if ($perMonth !== null && (int)$months !== 1): ?>
      <span class="dpc-permonth">&asymp; &#8377;<?= number_format($perMonth) ?> / month, billed once for <?= _dpc_e(plan_duration_text($days)) ?></span>
    <?php else: ?>
      <span class="dpc-permonth">Billed once every <?= _dpc_e(plan_duration_text($days)) ?></span>
    <?php endif; ?>

    <?php if ($feats): ?>
      <ul class="dpc-feats">
        <?php foreach ($feats as $f): ?><li><i class="fas fa-check"></i><?= _dpc_e($f) ?></li><?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div style="flex:1;"></div>
    <?php endif; ?>

    <?php if ($ctaMode === 'subscribe'): ?>
      <button type="button" class="dpc-cta dpc-subscribe" data-plan-id="<?= (int)$p['id'] ?>"
              data-plan-name="<?= _dpc_e($p['name']) ?>" data-plan-price="<?= number_format($price) ?>">
        Subscribe &ndash; &#8377;<?= number_format($price) ?>
      </button>
    <?php else: ?>
      <a class="dpc-cta" href="<?= _dpc_e($signupUrl) ?>">Join &amp; Choose This Plan</a>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<div class="dpc-settlement-note">
  <i class="fas fa-university"></i>
  <div>
    <strong>Fast, transparent payouts.</strong> Once you add your bank details, every completed and paid consultation
    is settled straight to your account within <strong>2 days (T+2)</strong> — the platform keeps a flat 10% fee, you get the rest.
  </div>
</div>
<?php if ($ctaMode === 'subscribe'): ?><div class="dpc-msg" id="dpcSubscribeMsg"></div><?php endif; ?>
<?php
}

}
