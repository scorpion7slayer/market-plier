<?php

$_toastSuccessMsg = '';
$_toastErrorMsg   = '';

// URL params (raw text already urlencoded by sender)
if (!empty($_GET['success'])) {
  $_toastSuccessMsg = $_GET['success'];
}
if (!empty($_GET['error'])) {
  $_toastErrorMsg = $_GET['error'];
}

// PHP variable override (takes priority if set)
if (!empty($toastSuccess)) {
  $_toastSuccessMsg = $toastSuccess;
}
if (!empty($toastError)) {
  $_toastErrorMsg = $toastError;
}
?>

<!-- ═══ TOAST NOTIFICATIONS ═══════════════════════════════ -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="mpToastContainer" style="z-index: 9999;"></div>
<script>
  function mpShowToast(message, type) {
    var container = document.getElementById('mpToastContainer');
    if (!container) return;
    var isError = (type === 'error');
    var div = document.createElement('div');
    div.className = 'toast align-items-center text-white border-0 mp-toast' + (isError ? ' bg-danger' : '');
    if (!isError) div.style.backgroundColor = '#7fb885';
    div.setAttribute('role', 'alert');
    div.setAttribute('aria-live', 'assertive');
    div.setAttribute('aria-atomic', 'true');
    var icon = isError ? 'fa-circle-exclamation' : 'fa-check';
    div.innerHTML = '<div class="d-flex"><div class="toast-body"><i class="fa-solid ' + icon + ' me-2"></i>' +
      message.replace(/</g, '&lt;').replace(/>/g, '&gt;') +
      '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button></div>';
    container.appendChild(div);
    new bootstrap.Toast(div, {
      delay: 4000
    }).show();
    div.addEventListener('hidden.bs.toast', function() {
      div.remove();
    });
  }
  <?php if ($_toastSuccessMsg || $_toastErrorMsg): ?>
    document.addEventListener('DOMContentLoaded', function() {
      <?php if ($_toastSuccessMsg): ?>
        mpShowToast(<?= json_encode($_toastSuccessMsg, JSON_HEX_TAG | JSON_HEX_AMP) ?>, 'success');
      <?php endif; ?>
      <?php if ($_toastErrorMsg): ?>
        mpShowToast(<?= json_encode($_toastErrorMsg, JSON_HEX_TAG | JSON_HEX_AMP) ?>, 'error');
      <?php endif; ?>
      // Clean URL params so refresh doesn't re-show the toast
      if (window.history.replaceState) {
        var url = new URL(window.location);
        url.searchParams.delete('success');
        url.searchParams.delete('error');
        window.history.replaceState({}, '', url);
      }
    });
  <?php endif; ?>
</script>