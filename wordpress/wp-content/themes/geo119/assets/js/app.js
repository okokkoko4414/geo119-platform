/**
 * GEO119 WordPress Theme - Frontend JavaScript
 * Language switcher, mobile menu, modals — vanilla JS, no bundler required.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    initLanguageSwitcher();
    initMobileMenu();
    initModals();
  });

  function initLanguageSwitcher() {
    var switchers = document.querySelectorAll('[data-language-switcher]');
    switchers.forEach(function (switcher) {
      switcher.addEventListener('change', function (e) {
        var locale = e.target.value;
        if (!locale) return;
        var currentUrl = new URL(window.location.href);
        var pathParts = currentUrl.pathname.replace(/^\/+|\/+$/g, '').split('/');
        var supported = (window.geo119Data && window.geo119Data.supportedLocales) || ['en'];

        if (supported.indexOf(pathParts[0]) !== -1) {
          pathParts[0] = locale;
        } else {
          pathParts.unshift(locale);
        }

        currentUrl.pathname = '/' + pathParts.join('/');
        window.location.href = currentUrl.toString();
      });
    });
  }

  function initMobileMenu() {
    var toggle = document.querySelector('[data-mobile-menu-toggle]');
    var menu = document.querySelector('[data-mobile-menu]');
    if (!toggle || !menu) return;

    toggle.addEventListener('click', function () {
      var expanded = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', String(!expanded));
      menu.classList.toggle('hidden');
    });
  }

  function initModals() {
    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-modal-open]');
      if (trigger) {
        var modalId = trigger.getAttribute('data-modal-open');
        var modal = document.getElementById(modalId);
        if (modal && typeof modal.showModal === 'function') {
          modal.showModal();
        }
      }

      var closer = e.target.closest('[data-modal-close]');
      if (closer) {
        var modal = closer.closest('dialog');
        if (modal && typeof modal.close === 'function') {
          modal.close();
        }
      }
    });

    document.addEventListener('click', function (e) {
      if (e.target.tagName === 'DIALOG' && e.target.open) {
        var rect = e.target.getBoundingClientRect();
        if (
          e.clientX < rect.left ||
          e.clientX > rect.right ||
          e.clientY < rect.top ||
          e.clientY > rect.bottom
        ) {
          e.target.close();
        }
      }
    });
  }
})();
