<?php
require_once 'models/HeroSettings.php';

// Get active hero settings
$heroSettings = new HeroSettings();
$hero = $heroSettings->getHeroSettings();

// Default values if no settings found
if (!$hero) {
    $hero = [
        'subtitle' => 'The season 1',
        'title' => 'TOURNAMENTS',
        'banner_image_url' => 'assets/images/hero-banner.jpg',
        'primary_btn_text' => '+ticket',
        'primary_btn_icon' => 'wallet-outline',
        'secondary_btn_text' => 'Games',
        'secondary_btn_icon' => 'game-controller-outline',
        'secondary_btn_url' => '#games'
    ];
}
?>

<!-- Hero Section -->
<section class="hero-section d-flex align-items-center" id="hero">
    <div class="container">
        <div class="row">
            <div class="col-lg-7 col-md-10">
                <div class="hero-content">
                    <?php if(isset($hero['subtitle']) && !empty($hero['subtitle'])): ?>
                    <div class="hero-subtitle">
                        <span><?= htmlspecialchars($hero['subtitle']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(isset($hero['title']) && !empty($hero['title'])): ?>
                    <h1 class="hero-title">
                        <?= htmlspecialchars($hero['title']) ?>
                    </h1>
                    <?php endif; ?>
                    
                    <div class="hero-buttons">
                        <?php if(isset($hero['primary_btn_text']) && !empty($hero['primary_btn_text'])): ?>
                        <a href="#" class="btn primary-btn">
                            <?php if(isset($hero['primary_btn_icon']) && !empty($hero['primary_btn_icon'])): ?>
                            <ion-icon name="<?= htmlspecialchars($hero['primary_btn_icon']) ?>"></ion-icon>
                            <?php endif; ?>
                            <?= htmlspecialchars($hero['primary_btn_text']) ?>
                        </a>
                        <?php endif; ?>
                        
                        <?php if(isset($hero['secondary_btn_text']) && !empty($hero['secondary_btn_text'])): ?>
                        <a href="<?= htmlspecialchars($hero['secondary_btn_url'] ?? '#') ?>" class="btn secondary-btn">
                            <?php if(isset($hero['secondary_btn_icon']) && !empty($hero['secondary_btn_icon'])): ?>
                            <ion-icon name="<?= htmlspecialchars($hero['secondary_btn_icon']) ?>"></ion-icon>
                            <?php endif; ?>
                            <?= htmlspecialchars($hero['secondary_btn_text']) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Hero Banner Background -->
    <?php if(isset($hero['banner_image_url']) && !empty($hero['banner_image_url'])): ?>
    <div class="hero-banner" style="background-image: url('<?= htmlspecialchars($hero['banner_image_url']) ?>')"></div>
    <?php else: ?>
    <div class="hero-banner" style="background-image: url('assets/images/hero-banner.jpg')"></div>
    <?php endif; ?>
</section>

<style>
    .hero-section {
        position: relative;
        height: 650px;
        overflow: hidden;
        background-color: #0A0F18;
        color: #fff;
    }
    
    .hero-banner {
        position: absolute;
        top: 0;
        right: 0;
        width: 100%;
        height: 100%;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        z-index: 1;
        opacity: 0.6;
    }
    
    .hero-content {
        position: relative;
        z-index: 2;
        padding: 30px 0;
    }
    
    .hero-subtitle {
        font-size: 24px;
        margin-bottom: 15px;
        color: #5966FF;
        font-weight: 500;
    }
    
    .hero-title {
        font-size: 72px;
        font-weight: 800;
        margin-bottom: 30px;
        text-transform: uppercase;
        letter-spacing: 1px;
        line-height: 1.1;
    }
    
    .hero-buttons {
        display: flex;
        gap: 15px;
    }
    
    .hero-buttons .btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 16px;
        transition: all 0.3s ease;
    }
    
    .hero-buttons .primary-btn {
        background-color: #5966FF;
        color: #fff;
        border: none;
    }
    
    .hero-buttons .primary-btn:hover {
        background-color: #4853e0;
        transform: translateY(-3px);
    }
    
    .hero-buttons .secondary-btn {
        background-color: rgba(255, 255, 255, 0.1);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .hero-buttons .secondary-btn:hover {
        background-color: rgba(255, 255, 255, 0.2);
        transform: translateY(-3px);
    }
    
    .hero-buttons ion-icon {
        font-size: 20px;
    }
    
    @media (max-width: 992px) {
        .hero-section {
            height: 550px;
        }
        
        .hero-title {
            font-size: 54px;
        }
    }
    
    @media (max-width: 768px) {
        .hero-section {
            height: 500px;
        }
        
        .hero-title {
            font-size: 42px;
        }
        
        .hero-subtitle {
            font-size: 20px;
        }
    }
    
    @media (max-width: 576px) {
        .hero-section {
            height: 450px;
            text-align: center;
        }
        
        .hero-title {
            font-size: 36px;
        }
        
        .hero-buttons {
            justify-content: center;
        }
    }
</style> 