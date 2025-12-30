<?php
// includes/sidebar-right.php
// Bu dosya ads_logic.php'nin daha önce dahil edilmiş olmasını gerektirir
?>
<!-- Right Sponsor Sidebar -->
<aside class="sponsor-sidebar sponsor-right">
    <?php if (!empty($sidebarRightAds)): ?>
        <?php $rAd = $sidebarRightAds[0]; ?>
        <a href="<?php echo escape($rAd['link_url'] ?: '#'); ?>" target="_blank">
            <img src="<?php echo BASE_URL . '/' . escape($rAd['image_url']); ?>" alt="<?php echo escape($rAd['title']); ?>">
        </a>
    <?php else: ?>
        <div class="sponsor-placeholder" style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 30px; text-align: center;">
            <div style="font-size: 1.5rem;">📢</div>
        </div>
    <?php endif; ?>
</aside>

