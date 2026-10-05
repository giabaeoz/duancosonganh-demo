<?php if (!empty($_SESSION['user_id'])): ?>
  </div><!-- .page -->
</main>
<?php else: ?>
  </div><!-- .guest-container -->
</div><!-- .guest-wrapper -->
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const btnMenu = document.getElementById('btnMenu');
  const sidebar = document.getElementById('sidebar');
  if (btnMenu && sidebar) {
    btnMenu.addEventListener('click', (e) => {
      e.stopPropagation();
      sidebar.classList.toggle('open');
    });
    document.addEventListener('click', (e) => {
      if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== btnMenu) {
        sidebar.classList.remove('open');
      }
    });
  }
</script>
<?= $extra_js ?? '' ?>
</body>
</html>