<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$pageTitle = 'Request a Website Build or Revamp | Diesel Designs';
$metaDescription = 'Request a website build, website revamp, Shopify make-over, graphics update, or visual website refresh from Diesel Designs.';

$projectTypes = ['New website build', 'Website revamp', 'Shopify make-over', 'Graphics/color customization', 'Not sure yet'];
$platforms = ['Shopify', 'WordPress', 'Custom PHP/website', 'Wix', 'Squarespace', 'Other', 'Not sure'];
$serviceOptions = ['New website', 'Website redesign/revamp', 'Shopify theme refresh', 'Homepage redesign', 'Graphics update', 'Color/branding update', 'SEO help', 'Mobile layout review', 'Not sure yet'];
$budgetRanges = ['Under $250', '$250–$500', '$500–$1,000', '$1,000+', 'Not sure yet'];
$timelines = ['ASAP', '1–2 weeks', '2–4 weeks', '1–2 months', 'Flexible'];
$contactMethods = ['Email', 'Facebook', 'Phone/text', 'No preference'];
$errors = [];
$success = false;
$values = $_POST;
$maxLengths = [
    'name' => 190,
    'email' => 190,
    'phone' => 100,
    'business_name' => 190,
    'preferred_contact_method' => 50,
    'project_type' => 100,
    'platform' => 100,
    'current_website_url' => 500,
    'budget_range' => 100,
    'timeline' => 100,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (trim($_POST['website_url_confirm'] ?? '') !== '') {
        $success = true;
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $projectType = $_POST['project_type'] ?? '';
        $description = trim($_POST['project_description'] ?? '');
        $budgetRange = $_POST['budget_range'] ?? '';
        $timeline = $_POST['timeline'] ?? '';
        $currentUrl = trim($_POST['current_website_url'] ?? '');
        $platform = $_POST['platform'] ?? '';
        $preferredContact = $_POST['preferred_contact_method'] ?? '';
        $servicesNeeded = $_POST['services_needed'] ?? [];

        foreach ($maxLengths as $field => $maxLength) {
            if (strlen(trim((string) ($_POST[$field] ?? ''))) > $maxLength) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . " must be {$maxLength} characters or fewer.";
            }
        }

        if ($name === '') {
            $errors[] = 'Name is required.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }

        if (!in_array($projectType, $projectTypes, true)) {
            $errors[] = 'Choose a project type.';
        }

        if ($description === '') {
            $errors[] = 'Short project description is required.';
        }

        if (!in_array($budgetRange, $budgetRanges, true)) {
            $errors[] = 'Choose a budget range.';
        }

        if (!in_array($timeline, $timelines, true)) {
            $errors[] = 'Choose a timeline.';
        }

        if ($currentUrl !== '' && !valid_url_or_blank($currentUrl)) {
            $errors[] = 'Current website URL must be a valid http or https URL.';
        }

        if ($platform !== '' && !in_array($platform, $platforms, true)) {
            $errors[] = 'Choose a valid platform option.';
        }

        if ($preferredContact !== '' && !in_array($preferredContact, $contactMethods, true)) {
            $errors[] = 'Choose a valid preferred contact method.';
        }

        $selectedServices = [];
        foreach ((array) $servicesNeeded as $service) {
            if (in_array($service, $serviceOptions, true)) {
                $selectedServices[] = $service;
            }
        }

        if (!$errors) {
            db()->prepare(
                'INSERT INTO website_requests '
                . '(name,email,phone,business_name,preferred_contact_method,project_type,platform,current_website_url,services_needed,project_description,inspiration_links,budget_range,timeline,notes,status,created_at) '
                . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
            )->execute([
                $name,
                $email,
                trim($_POST['phone'] ?? '') ?: null,
                trim($_POST['business_name'] ?? '') ?: null,
                $preferredContact ?: null,
                $projectType,
                $platform ?: null,
                $currentUrl ?: null,
                $selectedServices ? implode(', ', $selectedServices) : null,
                $description,
                trim($_POST['inspiration_links'] ?? '') ?: null,
                $budgetRange,
                $timeline,
                trim($_POST['notes'] ?? '') ?: null,
                'new',
            ]);

            $success = true;
            $values = [];
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <p class="eyebrow">Project Request</p>
    <h1>Request a Website Build or Revamp</h1>
    <p>Use this form for website build, revamp, Shopify make-over, or visual refresh project requests. Your request will be saved for review, and Diesel Designs will follow up using your preferred contact details.</p>
    <p>Have a quick question or general message instead? Email <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>.</p>
</section>
<section class="section">
    <?php if ($success): ?>
        <div class="empty success-message">
            <h2>Request received</h2>
            <p>Thank you! Diesel Designs will review your website build or revamp request and follow up. No exact turnaround time is promised until the project details are reviewed.</p>
            <p><a class="btn" href="portfolio.php">View Portfolio Work</a></p>
        </div>
    <?php else: ?>
        <?php foreach ($errors as $error): ?><p class="error-text"><?= e($error) ?></p><?php endforeach; ?>
        <form method="post" class="admin-form request-form">
            <?= csrf_field() ?>
            <label class="honeypot" aria-hidden="true">Leave this field blank<input name="website_url_confirm" value="" tabindex="-1" autocomplete="off"></label>
            <label>Name *<input name="name" maxlength="190" value="<?= e($values['name'] ?? '') ?>" required></label>
            <label>Email *<input type="email" name="email" maxlength="190" value="<?= e($values['email'] ?? '') ?>" required></label>
            <label>Business name<input name="business_name" maxlength="190" value="<?= e($values['business_name'] ?? '') ?>"></label>
            <label>Phone number<input name="phone" maxlength="100" value="<?= e($values['phone'] ?? '') ?>"></label>
            <label>Preferred contact method<select name="preferred_contact_method"><option value="">Choose one</option><?php foreach ($contactMethods as $option): ?><option value="<?= e($option) ?>" <?= ($values['preferred_contact_method'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
            <label>Project type *<select name="project_type" required><option value="">Choose one</option><?php foreach ($projectTypes as $option): ?><option value="<?= e($option) ?>" <?= ($values['project_type'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
            <label>Platform<select name="platform"><option value="">Choose one</option><?php foreach ($platforms as $option): ?><option value="<?= e($option) ?>" <?= ($values['platform'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
            <label>Current website URL<input type="url" name="current_website_url" maxlength="500" value="<?= e($values['current_website_url'] ?? '') ?>"><small>Optional, but must start with http:// or https:// if provided.</small></label>
            <fieldset class="form-fieldset"><legend>Services needed</legend><?php foreach ($serviceOptions as $option): ?><label class="check"><input type="checkbox" name="services_needed[]" value="<?= e($option) ?>" <?= in_array($option, (array) ($values['services_needed'] ?? []), true) ? 'checked' : '' ?>> <?= e($option) ?></label><?php endforeach; ?></fieldset>
            <label>Short project description *<textarea name="project_description" required><?= e($values['project_description'] ?? '') ?></textarea></label>
            <label>Inspiration links<textarea name="inspiration_links"><?= e($values['inspiration_links'] ?? '') ?></textarea></label>
            <label>Budget range *<select name="budget_range" required><option value="">Choose one</option><?php foreach ($budgetRanges as $option): ?><option value="<?= e($option) ?>" <?= ($values['budget_range'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
            <label>Timeline *<select name="timeline" required><option value="">Choose one</option><?php foreach ($timelines as $option): ?><option value="<?= e($option) ?>" <?= ($values['timeline'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
            <label>Additional notes<textarea name="notes"><?= e($values['notes'] ?? '') ?></textarea></label>
            <button class="btn btn-accent">Submit Website Request</button>
        </form>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
