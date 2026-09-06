<footer class="fhm-footer" id="fhm-footer">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <h5 class="fhm-brand-block">
          <span class="fhm-brand-mark" aria-hidden="true"><i class="fa-solid fa-server"></i></span>
          FreeHost Manager
        </h5>
        <p>Secure web hosting management. Hosting, files, databases, domains, DNS, SSL, backups, monitoring, provisioning and billing in one place.</p>
      </div>
      <div class="col-6 col-md-2">
        <h5>Product</h5>
        <a href="#fhm-features">Hosting</a>
        <a href="#fhm-features">File Manager</a>
        <a href="#fhm-features">Databases</a>
        <a href="#fhm-features">DNS</a>
        <a href="#fhm-features">SSL</a>
        <a href="#fhm-features">Backups</a>
        <a href="#fhm-features">Billing</a>
      </div>
      <div class="col-6 col-md-2">
        <h5>Resources</h5>
        <a href="#fhm-how">Documentation</a>
        <a href="#fhm-security">Security</a>
        <a href="#fhm-features">Monitoring</a>
        <a href="/login">Support</a>
      </div>
      <div class="col-md-4">
        <h5>Account</h5>
        <a href="/login">Sign In</a>
        <a href="/register">Create Account</a>
        <a href="/forgot-password">Forgot Password</a>
      </div>
    </div>
    <div class="fhm-bottom">
      <span>&copy; <?= date('Y') ?> FreeHost Manager. All rights reserved.</span>
      <span class="fhm-mark">FreeHost Manager</span>
    </div>
  </div>
</footer>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Smooth nav + active link on scroll
  document.querySelectorAll('a.nav-link[href^="#"]').forEach(function(a){
    a.addEventListener('click', function(){
      var c = document.querySelector('.navbar-collapse');
      if (c && c.classList.contains('show')) { new bootstrap.Collapse(c).hide(); }
    });
  });
  var sections = document.querySelectorAll('section[id]');
  var links = document.querySelectorAll('.fhm-nav .nav-link[href^="#"]');
  window.addEventListener('scroll', function(){
    var y = window.scrollY + 100;
    sections.forEach(function(s){
      if (s.offsetTop <= y && (s.offsetTop + s.offsetHeight) > y) {
        links.forEach(function(l){
          l.classList.toggle('active', l.getAttribute('href') === '#' + s.id);
        });
      }
    });
  });
</script>
</body>
</html>
