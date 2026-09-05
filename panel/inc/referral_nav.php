<?php
$referralTab = $referralTab ?? 'commission';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px" class="fade-up">
  <div style="display:flex;gap:4px;background:var(--sf);border:1px solid var(--bd);border-radius:10px;padding:4px;flex-wrap:wrap">
    <a href="affiliates.php" class="btn btn-sm <?= $referralTab === 'commission' ? 'btn-primary' : 'btn-ghost' ?>">Affiliate panel</a>
    <a href="ads.php" class="btn btn-sm <?= $referralTab === 'ads' ? 'btn-primary' : 'btn-ghost' ?>">Ads panel</a>
    <a href="referral.php" class="btn btn-sm <?= $referralTab === 'campaign' ? 'btn-primary' : 'btn-ghost' ?>">Free product campaign</a>
  </div>
</div>
