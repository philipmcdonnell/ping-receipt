<?php
declare(strict_types=1);
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Send Phil McDonnell a message that prints on his receipt printer.">
  <title>Send Phil a PING</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <main class="page-shell">
    <?php if (is_array($flash)): ?>
      <div class="notice notice--<?= $escape((string) $flash['type']) ?>" role="alert"><?= $escape((string) $flash['message']) ?></div>
    <?php endif; ?>
    <section class="receipt" aria-labelledby="page-title">
      <header class="receipt__header">
        <p class="eyebrow">A message from the internet</p>
        <h1 id="page-title">PING ME!</h1>
        <p>Send a receipt message to Phil McDonnell</p>
      </header>
      <form method="post" enctype="multipart/form-data" class="ping-form">
        <input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
        <label for="message">Your message</label>
        <textarea id="message" name="message" rows="7" maxlength="<?= $maxMessageLength ?>" placeholder="Type your message here..." aria-describedby="message-help character-count"><?= $escape($oldMessage) ?></textarea>
        <div class="field-meta"><span id="message-help">Printable text only</span><span id="character-count"><?= mb_strlen($oldMessage) ?> / <?= $maxMessageLength ?></span></div>
        <label for="image">Attach an image <span>(optional)</span></label>
        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="image-help">
        <p id="image-help" class="field-help">JPEG, PNG, or WebP up to <?= $maxImageMegabytes ?> MB. It will print in black and white.</p>
        <div class="image-preview" hidden><img alt="Selected image preview"><button type="button" class="remove-image">Remove image</button></div>
        <div class="receipt__note"><strong>THANKS FOR STOPPING BY</strong><span>Your PING will print here at my home</span></div>
        <button type="submit" class="send-button">Send PING <span aria-hidden="true">➤</span></button>
      </form>
    </section>
    <p class="privacy-note">Messages are stored for delivery and recovery. Images are removed after a successful print.</p>
  </main>
  <script>
    const message = document.querySelector('#message');
    const count = document.querySelector('#character-count');
    const image = document.querySelector('#image');
    const preview = document.querySelector('.image-preview');
    const previewImage = preview?.querySelector('img');
    const removeImage = preview?.querySelector('.remove-image');
    let previewUrl;
    message?.addEventListener('input', () => { count.textContent = `${message.value.length} / ${message.maxLength}`; });
    image?.addEventListener('change', () => {
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      const file = image.files?.[0];
      if (!file || !preview || !previewImage) { if (preview) preview.hidden = true; return; }
      previewUrl = URL.createObjectURL(file); previewImage.src = previewUrl; preview.hidden = false;
    });
    removeImage?.addEventListener('click', () => { image.value = ''; preview.hidden = true; if (previewUrl) URL.revokeObjectURL(previewUrl); });
  </script>
</body>
</html>
