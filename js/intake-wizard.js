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
        if (count) count.textContent = 'Step ' + (currentStep + 1) + ' of ' + steps.length;
        if (focusHeading) steps[currentStep].querySelector('h2')?.focus();
        updateReview();
    };

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

    const updateReview = () => {
        const value = (name) => form.querySelector('[name="' + name + '"]')?.value?.trim() || 'Not entered';
        const customer = customerSelect?.selectedOptions[0]?.textContent?.trim();
        const customerLine = customerSelect?.value ? customer : value('customer_name');
        const deviceLine = [value('device_type'), value('make'), value('model')].filter((item) => item !== 'Not entered').join(' · ');
        const review = document.querySelector('[data-intake-review]');
        if (!review) return;
        review.querySelector('[data-review-customer]').textContent = customerLine || 'Not entered';
        review.querySelector('[data-review-contact]').textContent = customerSelect?.value ? 'Existing customer' : value('contact_value');
        review.querySelector('[data-review-device]').textContent = deviceLine || 'Not entered';
        review.querySelector('[data-review-problem]').textContent = value('reported_symptoms');
        review.querySelector('[data-review-branch]').textContent = form.querySelector('[name="intake_branch_id"]')?.selectedOptions[0]?.textContent?.trim() || 'Not selected';
    };

    const validateStep = (index) => {
        const requiredFields = [...steps[index].querySelectorAll('input, select, textarea')]
            .filter((field) => field.required && !field.disabled);
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
    form.addEventListener('input', updateReview);
    form.addEventListener('change', updateReview);

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        updateCustomerFields();
        for (let index = 0; index < steps.length; index += 1) {
            showStep(index);
            if (!validateStep(index)) return;
        }
        HTMLFormElement.prototype.submit.call(form);
    });

    updateCustomerFields();
    updateContactType();
    showStep(0);
})();
