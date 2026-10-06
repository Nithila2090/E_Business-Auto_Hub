<?php
/**
 * AUTO HUB - Contact Us Page
 */

require_once __DIR__ . '/config/helpers.php';

$pageTitle = "Contact Us | AUTO HUB – Sri Lanka's Leading Auto Spare Parts Store";
$pageDescription = "Get in touch with AUTO HUB Sri Lanka. Contact our automotive technical specialists for spare parts inquiries, wholesale orders, and fitment verification.";

$feedback = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cName = trim($_POST['name'] ?? '');
    $cEmail = trim($_POST['email'] ?? '');
    $cPhone = trim($_POST['phone'] ?? '');
    $cSubject = trim($_POST['subject'] ?? 'New Customer Inquiry');
    $cMsg = trim($_POST['message'] ?? '');

    // Send notification to admin email
    if (!empty($cName) && !empty($cEmail)) {
        $subject = "AUTO HUB Contact Form: " . $cSubject;
        $body = "<h2>New Contact Form Inquiry</h2><p><strong>Name:</strong> {$cName}</p><p><strong>Email:</strong> {$cEmail}</p><p><strong>Phone:</strong> {$cPhone}</p><p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($cMsg)) . "</p>";
        send_mail('nithilarodrigo@gmail.com', 'AUTO HUB Admin', $subject, $body);
    }
    $feedback = "Thank you for reaching out! Your message has been received and an automotive specialist will contact you shortly.";
}

require_once __DIR__ . '/includes/header.php';
?>

  <!-- Banner -->
  <section style="background: linear-gradient(135deg, #070a12 0%, #0f172a 100%); color: white; padding: 4rem 0; border-bottom: 3px solid var(--primary-red); text-align: center;">
    <div class="container">
      <h1 style="font-size: 2.75rem; font-weight: 900; color: white; margin-bottom: 0.5rem;">
        GET IN <span class="text-red">TOUCH</span>
      </h1>
      <p style="font-size: 1.05rem; color: #94a3b8; max-width: 600px; margin: 0 auto;">
        Have a question about vehicle fitment, spare parts availability or delivery schedules? Our automotive technicians are here to help.
      </p>
    </div>
  </section>

  <!-- Main Contact Section -->
  <main class="shop-page-wrap">
    <div class="container">
      
      <?php if (!empty($feedback)): ?>
        <div style="background: #f0fdf4; color: #15803d; padding: 1rem 1.5rem; border-radius: 12px; font-size: 0.95rem; margin-bottom: 2rem; border: 1px solid #bbf7d0; text-align: center;">
          <i class="fas fa-check-circle"></i> <?php echo e($feedback); ?>
        </div>
      <?php endif; ?>

      <div style="display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 3rem; margin-bottom: 4rem;">
        
        <!-- Contact Form Box -->
        <div style="background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 2.5rem; box-shadow: var(--shadow-sm);">
          <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--text-main);">Send Us a Message</h2>
          <p style="font-size: 0.9rem; color: #64748b; margin-bottom: 2rem;">Fill out the form below and our team will respond within 2-4 business hours.</p>

          <form action="contact.php" method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Kasun Jayasuriya">
              </div>
              <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control" required placeholder="name@example.com">
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Phone Number *</label>
                <input type="tel" name="phone" class="form-control" required placeholder="070 727 5599">
              </div>
              <div class="form-group">
                <label class="form-label">Vehicle Details (Make, Model, Year)</label>
                <input type="text" name="vehicle" class="form-control" placeholder="e.g. Toyota Premio 2024 (NZT260)">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Subject *</label>
              <input type="text" name="subject" class="form-control" required placeholder="e.g. Inquiring about Brembo Brake Pads availability">
            </div>

            <div class="form-group">
              <label class="form-label">Message *</label>
              <textarea name="message" class="form-control" rows="5" required placeholder="Describe the spare part or assistance you need..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">
              <i class="fas fa-paper-plane"></i> Send Message
            </button>
          </form>
        </div>

        <!-- Contact Info Cards -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
          
          <div style="background: white; border: 1px solid var(--border-color); border-radius: 14px; padding: 1.5rem; display: flex; align-items: flex-start; gap: 1.25rem; box-shadow: var(--shadow-sm);">
            <div class="feature-icon" style="background: rgba(225, 29, 72, 0.1); color: var(--primary-red);">
              <i class="fas fa-map-marker-alt"></i>
            </div>
            <div>
              <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.35rem;">Main Distribution Center</h3>
              <p style="font-size: 0.9rem; color: #475569; line-height: 1.5;">
                No. 142, Galle Road, Colombo 03, Western Province, Sri Lanka
              </p>
            </div>
          </div>

          <div style="background: white; border: 1px solid var(--border-color); border-radius: 14px; padding: 1.5rem; display: flex; align-items: flex-start; gap: 1.25rem; box-shadow: var(--shadow-sm);">
            <div class="feature-icon" style="background: rgba(225, 29, 72, 0.1); color: var(--primary-red);">
              <i class="fas fa-phone-alt"></i>
            </div>
            <div>
              <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.35rem;">Direct Phone Lines</h3>
              <p style="font-size: 0.9rem; color: #475569; line-height: 1.5;">
                Hotline: <a href="tel:0707275599" style="color: inherit; font-weight: 700;">070 727 5599</a>
              </p>
            </div>
          </div>

          <div style="background: white; border: 1px solid var(--border-color); border-radius: 14px; padding: 1.5rem; display: flex; align-items: flex-start; gap: 1.25rem; box-shadow: var(--shadow-sm);">
            <div class="feature-icon" style="background: rgba(225, 29, 72, 0.1); color: var(--primary-red);">
              <i class="fas fa-envelope"></i>
            </div>
            <div>
              <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.35rem;">Email Addresses</h3>
              <p style="font-size: 0.9rem; color: #475569; line-height: 1.5;">
                General Support: <a href="mailto:nithilarodrigo@gmail.com" style="color: inherit; font-weight: 700;">nithilarodrigo@gmail.com</a>
              </p>
            </div>
          </div>

          <div style="background: white; border: 1px solid var(--border-color); border-radius: 14px; padding: 1.5rem; display: flex; align-items: flex-start; gap: 1.25rem; box-shadow: var(--shadow-sm);">
            <div class="feature-icon" style="background: rgba(225, 29, 72, 0.1); color: var(--primary-red);">
              <i class="fas fa-clock"></i>
            </div>
            <div>
              <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.35rem;">Operating Hours</h3>
              <p style="font-size: 0.9rem; color: #475569; line-height: 1.5;">
                Monday – Saturday: 8:30 AM – 7:00 PM<br>
                Sunday: 9:00 AM – 2:00 PM
              </p>
            </div>
          </div>

        </div>

      </div>

      <!-- FAQ Section -->
      <section id="faq" style="margin-top: 3rem;">
        <div class="section-title-wrap">
          <h2 class="section-title">FREQUENTLY ASKED QUESTIONS</h2>
          <p class="section-subtitle">Quick answers to common questions about our auto parts store</p>
        </div>

        <div style="max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 1rem;">
          
          <details style="background: white; border: 1px solid var(--border-color); border-radius: 10px; padding: 1.25rem; cursor: pointer;">
            <summary style="font-weight: 700; font-size: 1rem; color: var(--text-main);"><i class="fas fa-question-circle text-red"></i> How does islandwide delivery work in Sri Lanka?</summary>
            <p style="margin-top: 0.75rem; font-size: 0.9rem; color: #475569; line-height: 1.6;">
              We dispatch all orders via express registered couriers across all 9 provinces. Orders in Western Province are delivered within 24 hours. Central, Southern, North Western, and outer provinces take 24–48 hours.
            </p>
          </details>

          <details style="background: white; border: 1px solid var(--border-color); border-radius: 10px; padding: 1.25rem; cursor: pointer;">
            <summary style="font-weight: 700; font-size: 1rem; color: var(--text-main);"><i class="fas fa-question-circle text-red"></i> How can I be 100% certain a part fits my car?</summary>
            <p style="margin-top: 0.75rem; font-size: 0.9rem; color: #475569; line-height: 1.6;">
              You can select your Make, Model, and Year in our compatibility selector. You can also provide your Vehicle Chassis Number during checkout, and our technical support team will cross-check the exact OEM part number before dispatching.
            </p>
          </details>
        </div>
      </section>

    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
