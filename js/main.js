/* ============================================================
   TAURUS CONSTRUTORA — main.js
   ============================================================ */

/* === NAVBAR SCROLL === */
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
  navbar.classList.toggle('scrolled', window.scrollY > 50);
}, { passive: true });

/* === MOBILE MENU === */
const navToggle = document.getElementById('navToggle');
const navMenu   = document.getElementById('navMenu');

navToggle.addEventListener('click', () => {
  navToggle.classList.toggle('open');
  navMenu.classList.toggle('open');
  document.body.style.overflow = navMenu.classList.contains('open') ? 'hidden' : '';
});

navMenu.querySelectorAll('.navbar__link').forEach(link => {
  link.addEventListener('click', () => {
    navToggle.classList.remove('open');
    navMenu.classList.remove('open');
    document.body.style.overflow = '';
  });
});

/* === REVEAL ON SCROLL === */
const revealEls = document.querySelectorAll('.reveal');
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('visible');
      revealObserver.unobserve(e.target);
    }
  });
}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

revealEls.forEach(el => revealObserver.observe(el));

/* === COUNTER ANIMATION === */
function animateCounter(el) {
  const target = parseInt(el.dataset.count, 10);
  const duration = 1600;
  const start = performance.now();

  function update(now) {
    const elapsed = now - start;
    const progress = Math.min(elapsed / duration, 1);
    const ease = 1 - Math.pow(1 - progress, 3);
    el.textContent = Math.floor(ease * target);
    if (progress < 1) requestAnimationFrame(update);
    else el.textContent = target;
  }
  requestAnimationFrame(update);
}

const counterObserver = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      animateCounter(e.target);
      counterObserver.unobserve(e.target);
    }
  });
}, { threshold: 0.5 });

document.querySelectorAll('.trust-bar__number').forEach(el => counterObserver.observe(el));

/* === PARTICLES (hero) === */
(function createParticles() {
  const container = document.getElementById('particles');
  if (!container) return;
  const count = window.innerWidth < 768 ? 12 : 24;

  for (let i = 0; i < count; i++) {
    const p = document.createElement('span');
    const size   = Math.random() * 3 + 1;
    const x      = Math.random() * 100;
    const y      = Math.random() * 100;
    const delay  = Math.random() * 6;
    const dur    = Math.random() * 8 + 6;
    const opacity = Math.random() * 0.3 + 0.05;

    p.style.cssText = `
      position: absolute;
      left: ${x}%; top: ${y}%;
      width: ${size}px; height: ${size}px;
      border-radius: 50%;
      background: rgba(201,168,76,${opacity});
      animation: floatParticle ${dur}s ${delay}s ease-in-out infinite alternate;
      pointer-events: none;
    `;
    container.appendChild(p);
  }

  if (!document.getElementById('particleStyle')) {
    const style = document.createElement('style');
    style.id = 'particleStyle';
    style.textContent = `
      @keyframes floatParticle {
        0%   { transform: translate(0, 0) scale(1); opacity: .15; }
        50%  { opacity: .5; }
        100% { transform: translate(${Math.random()*30-15}px, ${Math.random()*-60-20}px) scale(1.4); opacity: .1; }
      }
    `;
    document.head.appendChild(style);
  }
})();

/* === PHONE MASK === */
const telefoneInput = document.getElementById('telefone');
if (telefoneInput) {
  telefoneInput.addEventListener('input', (e) => {
    let v = e.target.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 10) {
      v = v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
    } else if (v.length > 6) {
      v = v.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
    } else if (v.length > 2) {
      v = v.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
    } else {
      v = v.replace(/^(\d{0,2})$/, '($1');
    }
    e.target.value = v;
  });
}

/* === FORM SUBMIT — AJAX → mail.php === */
const form        = document.getElementById('contatoForm');
const formSuccess = document.getElementById('formSuccess');
const submitBtn   = form ? form.querySelector('[type="submit"]') : null;

if (form) {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const nome     = form.nome.value.trim();
    const telefone = form.telefone.value.trim();
    const tipo     = form.tipo.value;

    if (!nome || !telefone || !tipo) {
      highlightEmpty(form);
      return;
    }

    // Estado de carregamento
    submitBtn.disabled    = true;
    submitBtn.textContent = 'Enviando...';

    try {
      const body = new FormData(form);
      const res  = await fetch('mail.php', { method: 'POST', body });
      const data = await res.json();

      if (data.success) {
        form.style.display        = 'none';
        formSuccess.style.display = 'block';
      } else {
        showFormError(data.message || 'Erro ao enviar. Tente novamente.');
        submitBtn.disabled    = false;
        submitBtn.textContent = 'Solicitar Contato';
      }
    } catch {
      showFormError('Erro de conexão. Tente novamente ou use o WhatsApp.');
      submitBtn.disabled    = false;
      submitBtn.textContent = 'Solicitar Contato';
    }
  });
}

function highlightEmpty(form) {
  ['nome', 'telefone', 'tipo'].forEach(name => {
    const el = form[name];
    if (!el.value.trim()) {
      el.style.borderColor = '#e05252';
      el.addEventListener('input', () => { el.style.borderColor = ''; }, { once: true });
    }
  });
}

function showFormError(msg) {
  let err = form.querySelector('.form-error');
  if (!err) {
    err = document.createElement('p');
    err.className = 'form-error';
    err.style.cssText = 'color:#e05252;font-size:.85rem;margin-top:10px;text-align:center;';
    submitBtn.insertAdjacentElement('afterend', err);
  }
  err.textContent = msg;
}

/* === SMOOTH ANCHOR WITH OFFSET === */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', (e) => {
    const target = document.querySelector(anchor.getAttribute('href'));
    if (!target) return;
    e.preventDefault();
    const offset = 80;
    const top = target.getBoundingClientRect().top + window.scrollY - offset;
    window.scrollTo({ top, behavior: 'smooth' });
  });
});
