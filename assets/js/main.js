document.addEventListener('DOMContentLoaded', function () {

    // ─── THEME TOGGLE (dark mode) ──────────────────────────────────────────
    var modeToggle = document.getElementById('modeToggle');
    if (modeToggle) {
        // Sinkronkan icon dengan tema aktif
        function syncThemeIcon() {
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            modeToggle.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            // Warna address bar browser HP ikut tema (Android Chrome, iOS Safari 15+)
            var meta = document.getElementById('metaThemeColor');
            if (meta) meta.setAttribute('content', isDark ? '#0f172a' : '#1a56db');
        }
        syncThemeIcon();

        modeToggle.addEventListener('click', function () {
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            var next = isDark ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            document.documentElement.setAttribute('data-bs-theme', next);
            try { localStorage.setItem('theme', next); } catch (e) {}
            syncThemeIcon();
        });
    }

    // ─── AUTO-SUBMIT FILTER (dropdown kategori/sort) ───────────────────────
    document.querySelectorAll('.auto-submit').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var form = document.getElementById('filterForm');
            if (form) form.submit();
        });
    });

    // ─── MODAL EDIT ORGANISASI (admin) ─────────────────────────────────────
    var editModal = document.getElementById('editOrgModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn) return;
            document.getElementById('editOrgId').value       = btn.dataset.id;
            document.getElementById('editOrgName').value     = btn.dataset.name;
            document.getElementById('editOrgEmoji').value    = btn.dataset.emoji;
            document.getElementById('editOrgCategory').value = btn.dataset.category;
            document.getElementById('editOrgDesc').value     = btn.dataset.description;
        });
    }

    // ─── RATING MODAL ──────────────────────────────────────────────────────
    var selectedRating = 0;
    var currentOrgId   = null;
    var labels = ['', 'Sangat Buruk 😤', 'Kurang Baik 😕', 'Cukup Baik 😐', 'Bagus 👍', 'Luar Biasa! 🌟'];

    var rateModal = document.getElementById('rateModal');
    if (rateModal) {
        rateModal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            currentOrgId = btn.dataset.orgId;
            document.getElementById('modalOrgName').textContent = btn.dataset.orgName;
            document.getElementById('formOrgId').value = btn.dataset.orgId;
            resetModal();
        });
    }

    // ─── Star picker ───────────────────────────────────────────────────────
    var starBtns = document.querySelectorAll('.star-pick');

    starBtns.forEach(function (star) {
        star.addEventListener('mouseover',  function () { highlightStars(parseInt(star.dataset.val)); });
        star.addEventListener('mouseleave', function () { highlightStars(selectedRating); });
        star.addEventListener('click', function () {
            selectedRating = parseInt(star.dataset.val);
            highlightStars(selectedRating);
            document.getElementById('formRating').value = selectedRating;

            var lbl = document.getElementById('starLabel');
            lbl.textContent = labels[selectedRating];
            lbl.style.color = selectedRating >= 4 ? '#059669' : selectedRating >= 3 ? '#d97706' : '#dc2626';

            document.getElementById('submitBtn').disabled = false;
        });
    });

    function highlightStars(val) {
        starBtns.forEach(function (s) {
            if (parseInt(s.dataset.val) <= val) {
                s.classList.replace('far', 'fas');
                s.classList.add('lit');
            } else {
                s.classList.replace('fas', 'far');
                s.classList.remove('lit');
            }
        });
    }

    // ─── Reset modal ───────────────────────────────────────────────────────
    function resetModal() {
        selectedRating = 0;
        highlightStars(0);

        var lbl = document.getElementById('starLabel');
        lbl.textContent = 'Pilih bintang...';
        lbl.style.color = '';

        document.getElementById('ratingComment').value = '';
        document.getElementById('formRating').value    = '';
        document.getElementById('submitBtn').disabled  = true;
    }

    // ─── Submit: isi hidden fields lalu submit form biasa ──────────────────
    document.getElementById('submitBtn')?.addEventListener('click', function () {
        if (!selectedRating || !currentOrgId) return;

        document.getElementById('formComment').value = document.getElementById('ratingComment').value.trim();

        // Loading state
        this.disabled  = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengirim...';

        // Submit form ke rate.php (bukan AJAX — lebih kompatibel di shared hosting)
        document.getElementById('ratingForm').submit();
    });

    // ─── Penghitung karakter komentar ──────────────────────────────────
    var commentBox = document.getElementById('ratingComment');
    if (commentBox && !document.getElementById('charCount')) {
        var counter = document.createElement('div');
        counter.id = 'charCount';
        counter.className = 'char-count';
        counter.textContent = '0/500';
        commentBox.parentNode.appendChild(counter);
        commentBox.addEventListener('input', function () {
            var len = commentBox.value.length;
            counter.textContent = len + '/500';
            counter.classList.toggle('limit', len >= 480);
        });
    }

    // ─── Auto-dismiss status alert ─────────────────────────────────────────
    var alertBox = document.getElementById('statusAlert');
    if (alertBox) {
        setTimeout(function () {
            alertBox.style.transition = 'opacity .5s';
            alertBox.style.opacity    = '0';
            setTimeout(function () { alertBox.remove(); }, 500);
        }, 4000);
    }

});
