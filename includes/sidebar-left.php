<?php
// includes/sidebar-left.php
// Bu dosya ads_logic.php'nin daha önce dahil edilmiş olmasını gerektirir
?>
<!-- Left Sponsor Sidebar -->
<aside class="sponsor-sidebar sponsor-left">
    <?php if (!empty($sidebarLeftAds)): ?>
        <?php $lAd = $sidebarLeftAds[0]; ?>
        <a href="<?php echo escape($lAd['link_url'] ?: '#'); ?>" target="_blank">
            <img src="<?php echo BASE_URL . '/' . escape($lAd['image_url']); ?>" alt="<?php echo escape($lAd['title']); ?>">
        </a>
    <?php else: ?>
        <div class="sponsor-placeholder" style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 30px; text-align: center;">
            <div style="font-size: 1.5rem;">📢</div>
        </div>
    <?php endif; ?>
</aside>

