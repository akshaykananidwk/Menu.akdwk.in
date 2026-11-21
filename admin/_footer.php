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
<script>toastr.options={positionClass:'toast-top-right',progressBar:true,timeOut:3500};</script>
<?php if (!empty($_SESSION['flash'])): ?>
<script>
<?php foreach ($_SESSION['flash'] as $f): ?>toastr[<?= json_encode($f['type']) ?>](<?= json_encode($f['msg'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, '', {timeOut: 7000});
<?php endforeach; unset($_SESSION['flash']); ?>
</script>
<?php endif; ?>
<script src="<?= e(assetUrl('assets/js/app.js')) ?>"></script>
<?php if (!empty($pageScript)) echo $pageScript; ?>
</body>
</html>
