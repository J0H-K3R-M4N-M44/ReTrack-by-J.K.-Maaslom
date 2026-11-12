(() => {
  const form = document.querySelector('[data-intake-wizard]');
  if (!form) return;

  const steps = [...form.querySelectorAll('[data-intake-step]')];
  const markers = [...document.querySelectorAll('[data-step-marker]')];
  const nextButtons = [...form.querySelectorAll('[data-step-next]')];
  const backButtons = [...form.querySelectorAll('[data-step-back]')];
  const customerSelect = form.querySelector('[name="customer_id"]');
  const newCustomerFields = form.querySelector('[data-new-customer-fields]');
  const contactType = form.querySelector('[name="contact_type"]');
  const contactValue = form.querySelector('[name="contact_value"]');
  let currentStep = 0;

  // Keep the step index in range and update visibility, progress and keyboard focus.
  const showStep = (index, focusHeading = false) => {
    currentStep = Math.max(0, Math.min(index, steps.length - 1));
    steps.forEach((step, stepIndex) => {
      step.hidden = stepIndex !== currentStep;
      step.setAttribute('aria-hidden', String(stepIndex !== currentStep));
    });
    markers.forEach((marker, markerIndex) => {
      marker.classList.toggle('is-current', markerIndex === currentStep);
      marker.classList.toggle('is-complete', markerIndex < currentStep);
      if (markerIndex === currentStep) marker.setAttribute('aria-current', 'step');
      else marker.removeAttribute('aria-current');
    });
    const count = document.querySelector('[data-step-count]');
    if (count) count.textContent = 'Step ' + (currentStep + 1) + ' of 3';
    if (focusHeading) steps[currentStep].querySelector('h2')?.focus();
    const searchPanel = document.querySelector('.intake-search-panel');
    if (searchPanel) searchPanel.hidden = currentStep !== 0;
  };

  // Only require new-customer details when an existing customer was not selected.
  const updateCustomerFields = () => {
    if (!customerSelect || !newCustomerFields) return;
    const isNew = customerSelect.value === '';
    newCustomerFields.hidden = !isNew;
    newCustomerFields.querySelectorAll('[data-new-customer-required]').forEach((field) => {
      field.required = isNew;
    });
  };

  const updateContactType = () => {
    if (!contactType || !contactValue) return;
    const isEmail = contactType.value === 'EMAIL';
    contactValue.type = isEmail ? 'email' : 'tel';
    contactValue.autocomplete = isEmail ? 'email' : 'tel';
    contactValue.placeholder = isEmail ? 'name@example.com' : '09xx xxx xxxx';
  };

  // The server repeats all checks. This only helps staff correct mistakes sooner.
  const validateStep = (index) => {
    const make = form.querySelector('[name="make"]');
    const model = form.querySelector('[name="model"]');
    if (make && model)
      make.setCustomValidity(
        make.value.trim() || model.value.trim() ? '' : 'Enter a brand or model.'
      );
    const otherDevice = form.querySelector('[name="device_other"]');
    if (otherDevice) {
      otherDevice.required = form.querySelector('[name="device_type"]').value === 'Other';
    }
    for (const [group, field] of [
      ['problems', 'reported_symptoms'],
      ['conditions', 'condition_notes'],
    ]) {
      const selected = [...form.querySelectorAll('[name="' + group + '[]"]:checked')].map(
        (input) => input.value
      );
      const details = form.querySelector('[name="' + field + '"]');
      let message = '';
      if (!selected.length && !details.value.trim())
        message = 'Select an option or add a short description.';
      if (selected.includes('Other') && !details.value.trim())
        message = 'Please describe Other briefly.';
      if (group === 'conditions' && selected.includes('No visible damage') && selected.length > 1)
        message = 'Choose No visible damage by itself, or select the observed damage.';
      details.setCustomValidity(message);
    }
    const requiredFields = [...steps[index].querySelectorAll('input, select, textarea')].filter(
      (field) => !field.disabled && !field.closest('[hidden]')
    );
    const firstInvalid = requiredFields.find((field) => !field.checkValidity());
    if (!firstInvalid) return true;
    firstInvalid.reportValidity();
    firstInvalid.focus();
    return false;
  };

  nextButtons.forEach((button) => {
    button.addEventListener('click', () => {
      updateCustomerFields();
      if (validateStep(currentStep)) showStep(currentStep + 1, true);
    });
  });
  backButtons.forEach((button) => {
    button.addEventListener('click', () => showStep(currentStep - 1, true));
  });

  customerSelect?.addEventListener('change', updateCustomerFields);
  contactType?.addEventListener('change', updateContactType);

  // Enter in the customer step advances instead of submitting hidden fields.
  form.addEventListener('submit', (event) => {
    updateCustomerFields();
    if (currentStep === 0) {
      event.preventDefault();
      if (validateStep(0)) showStep(1, true);
      return;
    }
    if (!validateStep(currentStep)) event.preventDefault();
    // A valid submission opens server-side review; it cannot save a draft yet.
  });
  [...nextButtons, ...backButtons].forEach((button) => {
    button.hidden = false;
  });

  updateCustomerFields();
  updateContactType();
  showStep(0);
})();
