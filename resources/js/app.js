import { loadStripe } from '@stripe/stripe-js';
import { loadScript } from '@paypal/paypal-js';

document.addEventListener('DOMContentLoaded', () => {
    initLanguageSwitcher();
    initModals();
    initMobileMenu();
});

function initLanguageSwitcher() {
    const switchers = document.querySelectorAll('[data-language-switcher]');
    switchers.forEach((switcher) => {
        switcher.addEventListener('change', (e) => {
            const locale = e.target.value;
            const form = switcher.closest('form');
            if (form) {
                form.querySelector('[name="locale"]').value = locale;
                form.submit();
            }
        });
    });
}

function initModals() {
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-modal-open]');
        if (trigger) {
            const modalId = trigger.dataset.modalOpen;
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.showModal();
            }
        }

        const closer = e.target.closest('[data-modal-close]');
        if (closer) {
            const modal = closer.closest('dialog');
            if (modal) {
                modal.close();
            }
        }
    });

    document.addEventListener('click', (e) => {
        if (e.target.tagName === 'DIALOG' && e.target.open) {
            const rect = e.target.getBoundingClientRect();
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

function initMobileMenu() {
    const toggle = document.querySelector('[data-mobile-menu-toggle]');
    const menu = document.querySelector('[data-mobile-menu]');
    if (toggle && menu) {
        toggle.addEventListener('click', () => {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', !expanded);
            menu.classList.toggle('hidden');
        });
    }
}

export async function initStripe(publishableKey) {
    return loadStripe(publishableKey);
}

export async function initPayPal(clientId) {
    return loadScript({ clientId, currency: 'USD' });
}
