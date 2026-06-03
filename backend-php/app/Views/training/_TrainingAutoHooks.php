<script>
(function TrainingAutoHooks() {
  const slugify = (value) => String(value || '')
    .toLowerCase()
    .replace(/&/g, ' and ')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  const routeNode = document.getElementById('screenContent')
    || document.getElementById('appMain')
    || document.body;
  const routeValue = routeNode
    ? String(routeNode.getAttribute('data-screen-route') || document.body.getAttribute('data-cbms-route') || '')
    : '';
  const routeKey = slugify(routeValue) || 'screen';
  const knownIds = new Set(Array.from(document.querySelectorAll('[id]'))
    .map((node) => String(node.id || '').trim())
    .filter(Boolean));
  const generatedCounters = Object.create(null);

  const makeUniqueId = (base) => {
    const normalized = slugify(base);
    if (normalized === '') {
      return '';
    }
    if (!knownIds.has(normalized)) {
      knownIds.add(normalized);
      return normalized;
    }

    let next = generatedCounters[normalized] || 2;
    let candidate = `${normalized}-${next}`;
    while (knownIds.has(candidate)) {
      next += 1;
      candidate = `${normalized}-${next}`;
    }
    generatedCounters[normalized] = next + 1;
    knownIds.add(candidate);
    return candidate;
  };

  const assignId = (element, base, role = '') => {
    if (!(element instanceof HTMLElement)) {
      return '';
    }
    const existing = String(element.getAttribute('id') || '').trim();
    if (existing !== '') {
      knownIds.add(existing);
      if (role !== '' && !element.getAttribute('data-cbms-standard-role')) {
        element.setAttribute('data-cbms-standard-role', role);
      }
      return existing;
    }

    const nextId = makeUniqueId(base);
    if (nextId === '') {
      return '';
    }

    element.id = nextId;
    element.setAttribute('data-cbms-generated-id', '1');
    element.setAttribute('data-training-generated-id', '1');
    if (role !== '') {
      element.setAttribute('data-cbms-standard-role', role);
    }

    if (element.matches('input, select, textarea')) {
      const wrapper = element.parentElement;
      if (wrapper) {
        const label = wrapper.querySelector('label:not([for]), label[for=""]');
        if (label instanceof HTMLLabelElement) {
          label.htmlFor = nextId;
        }
      }
    }

    return nextId;
  };

  const normalizeName = (value) => String(value || '')
    .replace(/\[\]/g, '')
    .replace(/\[/g, '-')
    .replace(/\]/g, '');

  const extractRouteAction = (rawUrl) => {
    if (!rawUrl) {
      return '';
    }
    try {
      const url = new URL(rawUrl, window.location.href);
      return slugify(url.searchParams.get('route') || '');
    } catch (error) {
      return '';
    }
  };

  const extractRecordToken = (element) => {
    const ignoredKeys = new Set([
      'route',
      'return',
      'fy',
      'ver',
      'page',
      'link_context',
      'training_scenario_id',
      'scenario_id',
    ]);

    const fromUrl = (rawUrl) => {
      if (!rawUrl) {
        return '';
      }
      try {
        const url = new URL(rawUrl, window.location.href);
        for (const [key, value] of url.searchParams.entries()) {
          const normalizedKey = String(key || '').toLowerCase();
          if (ignoredKeys.has(normalizedKey)) {
            continue;
          }
          if (!/(^id$|_id$|id$)/i.test(normalizedKey)) {
            continue;
          }
          const token = slugify(value);
          if (token !== '') {
            return token;
          }
        }
      } catch (error) {
        return '';
      }
      return '';
    };

    if (element instanceof HTMLAnchorElement) {
      return fromUrl(element.getAttribute('href') || '');
    }

    if (element instanceof HTMLFormElement) {
      const hiddenFields = element.querySelectorAll('input[type="hidden"][name]');
      for (const field of hiddenFields) {
        const fieldName = String(field.getAttribute('name') || '');
        if (!/(^id$|_id$|id$)/i.test(fieldName)) {
          continue;
        }
        const token = slugify(field.getAttribute('value') || '');
        if (token !== '' && token !== '0') {
          return token;
        }
      }
      return fromUrl(element.getAttribute('action') || '');
    }

    const parentForm = element.closest('form');
    if (parentForm instanceof HTMLFormElement) {
      return extractRecordToken(parentForm);
    }

    return '';
  };

  const inferActionIntent = (element) => {
    if (!(element instanceof HTMLElement)) {
      return '';
    }

    if (element.matches('[data-bs-toggle="tab"], [role="tab"]')) {
      const target = slugify(String(element.getAttribute('data-bs-target') || '').replace(/^#/, ''));
      return target !== '' ? `${target}-tab` : 'tab';
    }

    const visibleText = slugify(element.innerText || element.textContent || '');
    const actionUrl = element instanceof HTMLAnchorElement
      ? String(element.getAttribute('href') || '')
      : String(element.closest('form')?.getAttribute('action') || '');
    const title = slugify(element.getAttribute('title') || '');
    const name = slugify(element.getAttribute('name') || '');
    const value = slugify(element.getAttribute('value') || '');
    const routeAction = extractRouteAction(actionUrl);
    const source = [visibleText, title, name, value, routeAction].filter(Boolean).join(' ');

    const checks = [
      ['save-roles-btn', /save-roles|save roles/],
      ['export-pdf-btn', /export-pdf|export pdf|pdf/],
      ['export-excel-btn', /export-excel|export excel|excel/],
      ['upload-btn', /upload/],
      ['download-btn', /download/],
      ['view-usage-btn', /project-usage|view-usage|open-full-usage|usage/],
      ['create-btn', /create|new /],
      ['add-line-btn', /add-line|add line/],
      ['add-funding-btn', /add-funding|add funding/],
      ['add-btn', /\badd\b/],
      ['filter-btn', /filter/],
      ['reset-btn', /reset|clear/],
      ['back-btn', /\bback\b/],
      ['open-btn', /\bopen\b/],
      ['edit-btn', /\bedit\b/],
      ['submit-btn', /submit/],
      ['forward-btn', /forward/],
      ['return-btn', /return/],
      ['approve-btn', /approve/],
      ['cancel-btn', /cancel/],
      ['remove-btn', /remove/],
      ['delete-btn', /delete/],
      ['resume-btn', /resume/],
      ['start-btn', /start/],
      ['view-btn', /\bview\b/],
      ['save-btn', /save/],
    ];

    for (const [intent, pattern] of checks) {
      if (pattern.test(source)) {
        return intent;
      }
    }

    return '';
  };

  const processControls = (container) => {
    container.querySelectorAll('input, select, textarea').forEach((field) => {
      if (!(field instanceof HTMLElement)) {
        return;
      }
      if (String(field.getAttribute('type') || '').toLowerCase() === 'hidden') {
        return;
      }
      if (String(field.getAttribute('id') || '').trim() !== '') {
        return;
      }

      const fieldName = String(field.getAttribute('name') || '').trim();
      const placeholder = String(field.getAttribute('placeholder') || '').toLowerCase();
      const isSearchField = ['q', 'query', 'search'].includes(fieldName.toLowerCase())
        || placeholder.includes('search');

      if (isSearchField) {
        assignId(field, `${routeKey}-search-input`, 'field');
        return;
      }

      if (fieldName !== '') {
        assignId(field, `${routeKey}-${normalizeName(fieldName)}`, 'field');
        return;
      }

      const typeName = String(field.getAttribute('type') || field.tagName || 'field').toLowerCase();
      assignId(field, `${routeKey}-${typeName}`, 'field');
    });
  };

  const processForms = (container) => {
    container.querySelectorAll('form').forEach((form) => {
      if (!(form instanceof HTMLFormElement)) {
        return;
      }
      if (String(form.getAttribute('id') || '').trim() !== '') {
        return;
      }
      const routeAction = extractRouteAction(form.getAttribute('action') || '') || 'form';
      const token = extractRecordToken(form);
      const suffix = token !== '' ? `-${token}` : '';
      assignId(form, `${routeKey}-${routeAction}-form${suffix}`, 'form');
    });
  };

  const processTables = (container) => {
    container.querySelectorAll('table').forEach((table) => {
      if (!(table instanceof HTMLTableElement)) {
        return;
      }
      if (String(table.getAttribute('id') || '').trim() !== '') {
        return;
      }
      assignId(table, `${routeKey}-table`, 'table');
    });
  };

  const processSections = (container) => {
    container.querySelectorAll('.card, .modal, .alert').forEach((section) => {
      if (!(section instanceof HTMLElement)) {
        return;
      }
      if (String(section.getAttribute('id') || '').trim() !== '') {
        return;
      }
      const heading = section.querySelector('h1, h2, h3, h4, h5, h6');
      const headingSlug = slugify(heading?.textContent || '');
      let roleBase = 'section';
      if (section.classList.contains('modal')) {
        roleBase = 'modal';
      } else if (section.classList.contains('alert')) {
        roleBase = 'alert';
      } else if (section.classList.contains('card')) {
        roleBase = 'card';
      }
      const base = headingSlug !== ''
        ? `${routeKey}-${headingSlug}-${roleBase}`
        : `${routeKey}-${roleBase}`;
      assignId(section, base, 'section');
    });
  };

  const processActions = (container) => {
    container.querySelectorAll('button, a[href]').forEach((element) => {
      if (!(element instanceof HTMLElement)) {
        return;
      }
      if (String(element.getAttribute('id') || '').trim() !== '') {
        return;
      }
      const intent = inferActionIntent(element);
      const routeAction = element instanceof HTMLAnchorElement
        ? extractRouteAction(String(element.getAttribute('href') || ''))
        : extractRouteAction(String(element.getAttribute('formaction') || element.closest('form')?.getAttribute('action') || ''));
      const fallbackIntent = intent !== ''
        ? intent
        : (routeAction !== ''
            ? `${routeAction}${element instanceof HTMLAnchorElement ? '-link' : '-btn'}`
            : (element instanceof HTMLAnchorElement
                ? (element.classList.contains('btn') ? 'action-link' : '')
                : 'action-btn'));
      if (fallbackIntent === '') {
        return;
      }
      const token = extractRecordToken(element);
      const suffix = token !== '' ? `-${token}` : '';
      assignId(element, `${routeKey}-${fallbackIntent}${suffix}`, 'action');
    });
  };

  const applyTrainingIds = (container) => {
    const root = container instanceof HTMLElement ? container : document;
    processForms(root);
    processControls(root);
    processTables(root);
    processSections(root);
    processActions(root);
  };

  applyTrainingIds(document);

  document.addEventListener('DOMContentLoaded', () => {
    applyTrainingIds(document);
  });

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node instanceof HTMLElement) {
          applyTrainingIds(node);
        }
      });
    });
  });

  if (document.documentElement) {
    observer.observe(document.documentElement, { childList: true, subtree: true });
  }
})();
</script>
