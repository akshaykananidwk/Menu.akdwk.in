    </main>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<script>toastr.options={positionClass:'toast-top-right',progressBar:true,timeOut:3500};</script>
<script src="<?= e(assetUrl('assets/js/app.js')) ?>"></script>
<!-- Install as an app (desktop / phone) -->
<div id="installBar" class="shadow-lg" style="display:none;position:fixed;right:16px;bottom:16px;z-index:2000;max-width:360px;background:#fff;border-radius:14px;border:1px solid #e6e8ef;padding:14px 16px">
  <div class="d-flex gap-3 align-items-start">
    <img src="<?= e(BASE_URL) ?>/assets/img/icon-192.png" alt="" style="width:44px;height:44px;border-radius:10px">
    <div class="flex-grow-1">
      <div class="fw-semibold">Install the app on this device</div>
      <div class="small text-muted mb-2">Opens in its own window with an icon on your desktop / home screen — no browser tabs needed.</div>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-primary" id="installYes"><i class="bi bi-download"></i> Install</button>
        <button class="btn btn-sm btn-light" id="installLater">Later</button>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  if ('serviceWorker' in navigator) { navigator.serviceWorker.register('<?= e(BASE_URL) ?>/client/sw.js', {scope: '<?= e(BASE_URL) ?>/client/'}).catch(function(){}); }
  var bar = document.getElementById('installBar'), deferred = null;
  var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
  function snoozed(){ try { return +localStorage.getItem('ak_install_snooze') > Date.now(); } catch(e){ return false; } }
  window.addEventListener('beforeinstallprompt', function(e){
    e.preventDefault(); deferred = e;
    document.querySelectorAll('.js-install-app').forEach(function(b){ b.classList.remove('d-none'); });
    if (!standalone && !snoozed()) bar.style.display = 'block';
  });
  window.akInstallApp = function(){
    if (!deferred) { AK.toast('info', 'Use the browser menu (⋮) → "Install app" / "Add to Home screen".'); return; }
    deferred.prompt();
    deferred.userChoice.finally(function(){ deferred = null; bar.style.display = 'none'; });
  };
  document.getElementById('installYes').addEventListener('click', window.akInstallApp);
  document.getElementById('installLater').addEventListener('click', function(){
    bar.style.display = 'none';
    try { localStorage.setItem('ak_install_snooze', String(Date.now() + 3 * 86400000)); } catch(e){}
  });
  window.addEventListener('appinstalled', function(){
    bar.style.display = 'none';
    document.querySelectorAll('.js-install-app').forEach(function(b){ b.classList.add('d-none'); });
    if (window.AK && AK.toast) AK.toast('success', 'Installed! Open it from the desktop / Start menu icon.');
  });
})();
</script>
<?php if (!empty($pageScript)) echo $pageScript; ?>
</body>
</html>
