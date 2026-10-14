(function (Drupal, once) {
  'use strict';

  const comparisonKey = 'terragear.compare';

  function selection() {
    try {
      return JSON.parse(localStorage.getItem(comparisonKey) || '[]').map(Number).filter(Number.isInteger).slice(0, 4);
    }
    catch {
      return [];
    }
  }

  function saveSelection(ids) {
    localStorage.setItem(comparisonKey, JSON.stringify(ids));
    renderSelection();
  }

  function renderSelection() {
    const ids = selection();
    document.querySelectorAll('[data-compare-product]').forEach((checkbox) => {
      checkbox.checked = ids.includes(Number(checkbox.dataset.compareProduct));
    });
    document.querySelectorAll('[data-compare-count]').forEach((count) => {
      count.textContent = String(ids.length);
    });
    document.querySelectorAll('[data-compare-tray]').forEach((tray) => {
      tray.classList.toggle('is-hidden', ids.length === 0);
      const summary = tray.querySelector('[data-compare-summary]');
      if (summary) summary.textContent = ids.length + ' of 4 products selected';
      const link = tray.querySelector('[data-compare-link]');
      if (link) link.href = '/compare?ids=' + ids.join(',');
    });
    document.querySelectorAll('.header-action[href="/compare"]').forEach((link) => {
      link.href = ids.length ? '/compare?ids=' + ids.join(',') : '/compare';
    });
  }

  Drupal.behaviors.terraGear = {
    attach(context) {
      once('terragear-compare', '[data-compare-product]', context).forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
          const id = Number(checkbox.dataset.compareProduct);
          let ids = selection().filter((value) => value !== id);
          if (checkbox.checked) {
            if (ids.length >= 4) {
              checkbox.checked = false;
              window.alert('Select up to four products to compare.');
              return;
            }
            ids.push(id);
          }
          saveSelection(ids);
        });
      });
      renderSelection();

      once('terragear-finder-tab', '[data-finder-tab]', context).forEach((button) => {
        button.addEventListener('click', () => {
          document.querySelectorAll('[data-finder-tab]').forEach((item) => item.classList.toggle('is-active', item === button));
          document.querySelectorAll('[data-finder-panel]').forEach((panel) => panel.classList.toggle('is-hidden', panel.dataset.finderPanel !== button.dataset.finderTab));
        });
      });

      once('terragear-machine', '[data-machine-finder]', context).forEach((form) => {
        const make = form.querySelector('[data-machine-make]');
        const type = form.querySelector('[data-machine-type]');
        const model = form.querySelector('[data-machine-model]');
        if (!make || !type || !model) return;
        const update = () => {
          model.querySelectorAll('option[data-make]').forEach((option) => {
            option.hidden = Boolean((make.value && option.dataset.make !== make.value) || (type.value && option.dataset.type !== type.value));
          });
          if (model.selectedOptions[0]?.hidden) model.value = '';
        };
        make.addEventListener('change', update);
        type.addEventListener('change', update);
      });

      once('terragear-gallery', '[data-gallery-thumb]', context).forEach((button) => {
        button.addEventListener('click', () => {
          const main = document.querySelector('[data-gallery-main]');
          if (main) main.src = button.dataset.galleryThumb;
          document.querySelectorAll('[data-gallery-thumb]').forEach((item) => item.classList.toggle('is-active', item === button));
        });
      });

      once('terragear-fitment', '[data-fitment-check]', context).forEach((form) => {
        form.addEventListener('submit', (event) => {
          event.preventDefault();
          const selected = Number(form.querySelector('select').value);
          const compatible = JSON.parse(form.dataset.compatibleModels || '[]').includes(selected);
          form.querySelector('output').textContent = compatible
            ? 'Confirmed: this part is listed for your machine.'
            : 'This model is not listed for this part. Check another product or contact a dealer.';
        });
      });

      once('terragear-pdp-tab', '[data-pdp-tab]', context).forEach((button) => {
        button.addEventListener('click', () => {
          document.querySelectorAll('[data-pdp-tab]').forEach((item) => item.classList.toggle('is-active', item === button));
          document.querySelectorAll('[data-pdp-panel]').forEach((panel) => panel.classList.toggle('is-hidden', panel.dataset.pdpPanel !== button.dataset.pdpTab));
        });
      });

      once('terragear-view-mode', '[data-view-mode]', context).forEach((button) => {
        button.addEventListener('click', () => {
          const table = button.dataset.viewMode === 'table';
          document.querySelector('[data-catalog-cards]')?.classList.toggle('is-hidden', table);
          document.querySelector('[data-catalog-table]')?.classList.toggle('is-hidden', !table);
          document.querySelectorAll('[data-view-mode]').forEach((item) => item.classList.toggle('is-active', item === button));
        });
      });

      once('terragear-sort', '[data-sort-select]', context).forEach((select) => {
        select.value = new URLSearchParams(window.location.search).get('sort') || 'part';
        select.addEventListener('change', () => {
          const url = new URL(window.location.href);
          url.searchParams.set('sort', select.value);
          window.location.assign(url.toString());
        });
      });

      once('terragear-range', '[data-range-for]', context).forEach((slider) => {
        const number = slider.closest('form')?.querySelector(`[name="${slider.dataset.rangeFor}"]`);
        slider.addEventListener('input', () => { if (number) number.value = slider.value; });
        number?.addEventListener('input', () => { slider.value = number.value || '0'; });
      });
      once('terragear-dealer-sort', '[data-dealer-sort]', context).forEach((select) => {
        select.addEventListener('change', () => {
          const url = new URL(window.location.href);
          url.searchParams.set('sort', select.value);
          window.location.assign(url.toString());
        });
      });

      once('terragear-testimonial', '[data-testimonial-slider]', context).forEach((slider) => {
        const slides = Array.from(slider.querySelectorAll('.testimonial'));
        if (!slides.length) return;
        let index = 0;
        const show = () => slides.forEach((slide, position) => slide.classList.toggle('is-hidden', position !== index));
        slider.querySelector('[data-slide-prev]')?.addEventListener('click', () => { index = (index - 1 + slides.length) % slides.length; show(); });
        slider.querySelector('[data-slide-next]')?.addEventListener('click', () => { index = (index + 1) % slides.length; show(); });
      });
    }
  };
})(Drupal, once);
