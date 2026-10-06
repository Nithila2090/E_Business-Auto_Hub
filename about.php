<?php
/**
 * AUTO HUB - About Us Page
 */

require_once __DIR__ . '/config/helpers.php';

$pageTitle = "About Us | AUTO HUB – Sri Lanka's Leading Auto Spare Parts Store";
$pageDescription = "Learn about AUTO HUB – Sri Lanka's premier automotive spare parts supplier. Genuine OEM components, islandwide delivery, warranty-backed parts.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- About Banner -->
  <section style="background: linear-gradient(135deg, #070a12 0%, #0f172a 100%); color: white; padding: 4.5rem 0; border-bottom: 3px solid var(--primary-red); text-align: center;">
    <div class="container">
      <div class="hero-badge-pill" style="margin: 0 auto 1rem;">
        <i class="fas fa-award"></i> Driven by Quality &bull; Trusted Across Sri Lanka
      </div>
      <h1 style="font-size: 3rem; font-weight: 900; color: white; margin-bottom: 1rem;">
        ABOUT <span class="text-red">AUTO HUB</span>
      </h1>
      <p style="font-size: 1.1rem; color: #94a3b8; max-width: 680px; margin: 0 auto; line-height: 1.6;">
        Sri Lanka's dedicated digital marketplace for genuine automotive spare parts, high performance replacements, and maintenance essentials.
      </p>
    </div>
  </section>

  <!-- Company Overview & Story -->
  <main class="shop-page-wrap">
    <div class="container">
      
      <div style="display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 3.5rem; align-items: center; margin-bottom: 5rem;">
        <div>
          <span style="font-family: var(--font-heading); color: var(--primary-red); font-weight: 700; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px;">Our Mission &amp; Purpose</span>
          <h2 style="font-size: 2.3rem; font-weight: 900; color: var(--text-main); line-height: 1.2; margin-top: 0.4rem; margin-bottom: 1.25rem;">
            Revolutionizing Automotive Spare Parts Sourcing in Sri Lanka
          </h2>
          <p style="line-height: 1.8; color: #475569; margin-bottom: 1.25rem;">
            Founded with a vision to eliminate counterfeit components and simplify car maintenance, <strong>AUTO HUB</strong> connects vehicle owners, automotive workshops, and fleet managers directly with certified OEM and tier-1 replacement parts.
          </p>
          <p style="line-height: 1.8; color: #475569; margin-bottom: 1.5rem;">
            Whether you drive a Toyota Axio or Premio through Colombo traffic, navigate Kandy hill climbs in a Honda Vezel, or haul heavy cargo in a Toyota Hilux, our catalog ensures 100% verified vehicle fitment, competitive prices, and express islandwide dispatch.
          </p>
          <div style="display: flex; gap: 1.5rem;">
            <a href="products.php" class="btn btn-primary btn-lg">Explore Catalog <i class="fas fa-arrow-right"></i></a>
            <a href="contact.php" class="btn btn-outline-dark btn-lg">Contact Our Experts</a>
          </div>
        </div>

        <div style="background: white; border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-xl);">
          <img src="images/hero/hero.jpg" alt="AUTO HUB Warehouse" style="border-radius: 14px; width: 100%; height: 320px; object-fit: cover;">
        </div>
      </div>

      <!-- Key Statistics Strip -->
      <div style="background: var(--dark-surface); border-radius: 20px; padding: 3rem 2rem; color: white; margin-bottom: 5rem; border: 1px solid #1e293b;">
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 2rem; text-align: center;">
          <div>
            <div style="font-family: var(--font-heading); font-size: 2.8rem; font-weight: 900; color: var(--primary-red);">10,000+</div>
            <div style="color: #94a3b8; font-size: 0.95rem; margin-top: 0.25rem;">Spare Parts in Stock</div>
          </div>
          <div>
            <div style="font-family: var(--font-heading); font-size: 2.8rem; font-weight: 900; color: var(--primary-red);">50,000+</div>
            <div style="color: #94a3b8; font-size: 0.95rem; margin-top: 0.25rem;">Happy Sri Lankan Drivers</div>
          </div>
          <div>
            <div style="font-family: var(--font-heading); font-size: 2.8rem; font-weight: 900; color: var(--primary-red);">9</div>
            <div style="color: #94a3b8; font-size: 0.95rem; margin-top: 0.25rem;">Provinces Covered</div>
          </div>
          <div>
            <div style="font-family: var(--font-heading); font-size: 2.8rem; font-weight: 900; color: var(--primary-red);">100%</div>
            <div style="color: #94a3b8; font-size: 0.95rem; margin-top: 0.25rem;">Genuine OEM Guarantee</div>
          </div>
        </div>
      </div>

      <!-- Why Choose Us Pillars -->
      <div class="section-title-wrap">
        <h2 class="section-title">WHY CHOOSE AUTO HUB</h2>
        <p class="section-subtitle">The four pillars of our automotive excellence</p>
      </div>

      <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 5rem;">
        <div class="feature-card" style="flex-direction: column; text-align: center; padding: 2rem 1.5rem;">
          <div class="feature-icon" style="margin: 0 auto 1rem; width: 60px; height: 60px; font-size: 1.75rem;">
            <i class="fas fa-shield-alt"></i>
          </div>
          <h3 class="feature-title" style="font-size: 1.15rem; margin-bottom: 0.5rem;">100% Genuine Parts</h3>
          <p class="feature-text">Direct sourcing from authorized distributors including Brembo, Denso, NGK, Bosch and KYB.</p>
        </div>

        <div class="feature-card" style="flex-direction: column; text-align: center; padding: 2rem 1.5rem;">
          <div class="feature-icon" style="margin: 0 auto 1rem; width: 60px; height: 60px; font-size: 1.75rem;">
            <i class="fas fa-shipping-fast"></i>
          </div>
          <h3 class="feature-title" style="font-size: 1.15rem; margin-bottom: 0.5rem;">Islandwide Express</h3>
          <p class="feature-text">Next-day delivery in Colombo &amp; Western Province; 48-hour delivery across all outer districts.</p>
        </div>

        <div class="feature-card" style="flex-direction: column; text-align: center; padding: 2rem 1.5rem;">
          <div class="feature-icon" style="margin: 0 auto 1rem; width: 60px; height: 60px; font-size: 1.75rem;">
            <i class="fas fa-check-double"></i>
          </div>
          <h3 class="feature-title" style="font-size: 1.15rem; margin-bottom: 0.5rem;">Guaranteed Fitment</h3>
          <p class="feature-text">Our intelligent vehicle compatibility selector ensures the part fits your exact chassis code.</p>
        </div>

        <div class="feature-card" style="flex-direction: column; text-align: center; padding: 2rem 1.5rem;">
          <div class="feature-icon" style="margin: 0 auto 1rem; width: 60px; height: 60px; font-size: 1.75rem;">
            <i class="fas fa-headset"></i>
          </div>
          <h3 class="feature-title" style="font-size: 1.15rem; margin-bottom: 0.5rem;">Expert Support</h3>
          <p class="feature-text">Speak directly with experienced automotive specialists via phone, email or WhatsApp.</p>
        </div>
      </div>

    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
