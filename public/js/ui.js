/* ============================================================================
   ui.js — the client-side half of the core components.
   ----------------------------------------------------------------------------
   Baustein.js is the framework runtime and knows nothing about any particular
   component. This file holds the small amount of behaviour that genuinely
   cannot live on the server:

     - toasts                a message that appears and fades
     - Slider                open / close / closeAll for side panels
     - Picker                option toggling, pills, search, outside-close
     - pointer tracking      pageX / pageY, which Event->absolute() reads
     - theme                 applying and remembering light / dark

   Everything here is delegated from `document`, so it keeps working on markup
   that arrives later from a server event — there is nothing to re-initialise.
============================================================================ */

(function () {
  'use strict';

  /* --------------------------------------------------------------- pointer */
  // Event->absolute() positions an element at the last known pointer
  // position, and reads these two globals to do it.
  window.pageX = 0;
  window.pageY = 0;
  document.addEventListener('mousemove', function (e) {
    window.pageX = e.pageX;
    window.pageY = e.pageY;
  }, { passive: true });

  /* ---------------------------------------------------------------- toasts */
  function stack() {
    var el = document.getElementById('toast-stack');
    if (!el) {
      el = document.createElement('div');
      el.id = 'toast-stack';
      el.className = 'toast-stack';
      document.body.appendChild(el);
    }
    return el;
  }

  /**
   * toast('Saved')                     neutral
   * toast('Saved', 'success')          success | error | warning | ''
   * toast('Saved', 'success', 6000)    custom lifetime in ms
   *
   * From the server:  Event::make()->call('toast', ['Saved', 'success'])
   */
  window.toast = function (message, tone, ms) {
    if (message === undefined || message === null || message === '') return;

    var node = document.createElement('div');
    node.className = 'toast' + (tone ? ' is-' + tone : '');
    node.setAttribute('role', 'status');
    node.textContent = String(message);

    stack().appendChild(node);

    var life = Number(ms) > 0 ? Number(ms) : 4000;
    setTimeout(function () {
      node.classList.add('is-leaving');
      setTimeout(function () { node.remove(); }, 220);
    }, life);
  };

  // Convenience aliases, so a handler can say what it means.
  window.successToaster = function (m) { window.toast(m, 'success'); };
  window.errorToaster   = function (m) { window.toast(m, 'error'); };
  window.warningToaster = function (m) { window.toast(m, 'warning'); };

  /* --------------------------------------------------------------- Slider */
  // Baustein.js looks for window.Slider.closeAll and calls it whenever an
  // interaction starts outside a .slider, so a panel showing stale context
  // cannot be left behind. That contract is why closeAll exists here.
  window.Slider = {
    open: function (id) {
      var el = typeof id === 'string' ? document.getElementById(id) : id;
      if (!el) return;
      el.classList.remove('hidden');
      // One frame between "displayed" and "open" so the transition has two
      // states to animate between.
      requestAnimationFrame(function () { el.classList.add('is-open'); });
    },

    close: function (id) {
      var el = typeof id === 'string' ? document.getElementById(id) : id;
      if (!el) return;
      el.classList.remove('is-open');
      setTimeout(function () { el.classList.add('hidden'); }, 180);
    },

    /** Close every open slider. Also removes ones the server marked disposable. */
    closeAll: function () {
      document.querySelectorAll('.slider.is-open').forEach(function (el) {
        window.Slider.close(el);
      });
    },

    toggle: function (id) {
      var el = typeof id === 'string' ? document.getElementById(id) : id;
      if (!el) return;
      if (el.classList.contains('is-open')) window.Slider.close(el);
      else window.Slider.open(el);
    }
  };

  /* --------------------------------------------------------------- Picker */
  function pickerRefresh(picker) {
    var state   = picker.querySelector('.picker-state');
    var summary = picker.querySelector('.picker-value');
    var options = picker.querySelectorAll('.picker-option input');
    var values  = [];
    var pills   = '';

    options.forEach(function (input) {
      if (!input.checked) return;
      values.push(input.value);
      var label = input.closest('.picker-option');
      var text  = label ? (label.getAttribute('data-label') || input.value) : input.value;
      var pill  = document.createElement('span');
      pill.className = 'picker-pill';
      pill.textContent = text;
      pills += pill.outerHTML;
    });

    if (summary) {
      summary.innerHTML = pills || '<span class="picker-placeholder">'
        + (picker.getAttribute('data-placeholder') || summary.textContent || '') + '</span>';
      if (!pills && !picker.getAttribute('data-placeholder')) {
        // Keep whatever placeholder the server rendered the first time.
        var existing = summary.querySelector('.picker-placeholder');
        if (existing) picker.setAttribute('data-placeholder', existing.textContent);
      }
    }

    if (state) {
      var joined = values.join('|');
      if (state.value !== joined) {
        state.value = joined;
        state.setAttribute('value', joined);
        // This is what reaches the server: Baustein binds the component's
        // xonchange handler to this hidden input, so the handler receives the
        // whole selection rather than the one option that was just clicked.
        state.dispatchEvent(new Event('change', { bubbles: false }));
      }
    }
  }

  document.addEventListener('change', function (e) {
    var input = e.target;
    if (!input || !input.closest) return;

    var option = input.closest('.picker-option');
    if (!option) return;

    var picker = input.closest('.picker');
    if (!picker) return;

    pickerRefresh(picker);

    // A single-choice picker has served its purpose once something is picked.
    if (picker.getAttribute('data-multi') === '0') picker.removeAttribute('open');
  });

  // Search box inside a picker filters its options.
  document.addEventListener('input', function (e) {
    var box = e.target;
    if (!box || !box.classList || !box.classList.contains('picker-search')) return;

    var picker = box.closest('.picker');
    if (!picker) return;

    var term = box.value.trim().toLowerCase();
    picker.querySelectorAll('.picker-option').forEach(function (option) {
      var label = (option.getAttribute('data-label') || option.textContent || '').toLowerCase();
      option.classList.toggle('hidden', term !== '' && label.indexOf(term) === -1);
    });
  });

  /* ------------------------------------------------- close on outside click */
  // Any <details> that behaves like a menu closes when the interaction starts
  // somewhere else. Add the class to your own dropdowns to get the same.
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.picker[open], details.menu[open]').forEach(function (node) {
      if (!node.contains(e.target)) node.removeAttribute('open');
    });
  }, true);

  // Escape closes the innermost open thing: a menu, then a modal, then a slider.
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;

    var open = document.querySelector('details.picker[open], details.menu[open]');
    if (open) { open.removeAttribute('open'); return; }

    var modal = document.querySelector('.modal-overlay');
    if (modal) { modal.remove(); return; }

    window.Slider.closeAll();
  });

  /* ---------------------------------------------------------------- theme */
  /**
   * Apply light or dark immediately.
   *
   * The server is the source of truth (State 'theme' drives the data-theme
   * attribute on first paint); this only avoids a round trip on the click.
   * A handler that also persists it looks like:
   *
   *   State::set('theme', $next);
   *   return Event::make()->call('applyTheme', [$next])->send();
   */
  window.applyTheme = function (name) {
    var theme = name === 'dark' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem('app.theme', theme); } catch (e) { /* private mode */ }
  };

  window.toggleTheme = function () {
    var current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    window.applyTheme(current === 'dark' ? 'light' : 'dark');
  };
})();
