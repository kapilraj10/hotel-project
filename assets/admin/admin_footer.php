<?php
// admin_footer.php
?>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Sidebar toggle
    document.addEventListener('DOMContentLoaded', function() {
      const sidebar = document.getElementById('adminSidebar');
      const toggle = document.getElementById('sidebarToggle');
      const body = document.body;
      
      // Check for saved state
      const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
      if (isCollapsed) {
        body.classList.add('sidebar-collapsed');
      }
      
      // Toggle sidebar collapse
      if (toggle) {
        toggle.addEventListener('click', function() {
          body.classList.toggle('sidebar-collapsed');
          localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
        });
      }
      
      // Mobile menu toggle for small screens
      if (window.innerWidth <= 992) {
        const mobileMenuBtn = document.createElement('button');
        mobileMenuBtn.className = 'btn btn-light ms-2';
        mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
        mobileMenuBtn.onclick = function() {
          sidebar.classList.toggle('show');
        };
        document.querySelector('.top-navbar .container-fluid').appendChild(mobileMenuBtn);
      }
    });
  </script>
</body>
</html>