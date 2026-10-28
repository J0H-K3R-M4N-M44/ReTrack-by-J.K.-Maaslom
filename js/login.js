const createLoginEventData = (form, emailValue, passwordValue) => ({
    email: emailValue || '(empty)',
    passwordProvided: passwordValue.length > 0,
    submittedAt: new Date().toISOString(),
    formId: form.id,
});

const hideMessageLater = () => {
    const messageArea = document.getElementById('message-area');

    if (!messageArea) {
        return;
    }

    setTimeout(() => {
        messageArea.hidden = true;
    }, 1000);
};

const handleLoginSubmit = (event, form, email, password, submitButton, state) => {
    event.preventDefault();

    if (state.isSubmitting) {
        console.log('Login submission ignored: submission already in progress.', {
            formId: form.id,
        });
        return;
    }

    console.log('Login submission started: submit event received.', {
        formId: form.id,
        submittedAt: new Date().toISOString(),
    });

    NotificationCenter.publish('login:status', { status: 'attempted' });

    const emailValue = email.value.trim();
    const passwordValue = password.value.trim();
    const loginEventData = createLoginEventData(form, emailValue, passwordValue);

    console.log('Event published: login:submitted.', loginEventData);
    NotificationCenter.publish('login:submitted', loginEventData);

    const validation = validateLoginInput(emailValue, passwordValue);

    if (!validation.valid) {
        handleInvalidLogin(validation.message, emailValue, email, password);
        return;
    }

    handleValidLogin(form, submitButton, state);
};

const validateLoginInput = (emailValue, passwordValue) => {
    const missingFields = [];

    if (!emailValue) missingFields.push('Email');
    if (!passwordValue) missingFields.push('Password');

    const result = {
        valid: missingFields.length === 0,
        missingFields,
        message: missingFields.length ? `Please fill in: ${missingFields.join(', ')}.` : '',
    };

    console.log('Login validation completed.', {
        valid: result.valid,
        missingFields: result.missingFields,
        message: result.message,
    });

    return result;
};

const handleInvalidLogin = (message, emailValue, email, password) => {
    console.log('Invalid login handled: required fields are missing.', {
        message,
        focusTarget: emailValue ? 'password' : 'email',
    });

    NotificationCenter.publish('login:status', {
        status: 'failed',
        message,
    });

    repairAlert.open('warning', 'Missing required fields', message, () => {
        hideMessageLater();

        requestAnimationFrame(() => {
            (emailValue ? password : email).focus();
        });
    });
};

const handleValidLogin = (form, submitButton, state) => {
    console.log('Valid login handled: starting credential transition.', {
        formId: form.id,
    });

    NotificationCenter.publish('login:status', { status: 'succeeded' });

    state.isSubmitting = true;

    if (submitButton) {
        submitButton.disabled = true;
    }

    startLoginTransition(form, submitButton);
};

const handlePageShow = (submitButton, originalButtonText, state) => {
    state.isSubmitting = false;

    if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = originalButtonText;
    }
};

const handleServerLoginError = () => {
    repairAlert.open(
        'error',
        'Access denied',
        'The email or password is incorrect.',
        hideMessageLater,
    );

    const url = new URL(window.location.href);
    url.searchParams.delete('error');
    window.history.replaceState({}, document.title, url);
};

const initializeLoginPage = () => {
    // DOM references and submission state.
    const form = document.getElementById('admin-login-form');

    if (!form) {
        return;
    }

    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton?.textContent || 'Login';
    const state = { isSubmitting: false };

    // Listener wiring delegates behavior to named handlers.
    form.addEventListener('submit', (event) => {
        handleLoginSubmit(event, form, email, password, submitButton, state);
    });

    // Restore the form after browser back-forward cache navigation.
    window.addEventListener('pageshow', () => {
        handlePageShow(submitButton, originalButtonText, state);
    });

    // Display and then remove the server-side authentication error from the URL.
    const url = new URL(window.location.href);

    if (url.searchParams.get('error') === '1') {
        handleServerLoginError();
    }
};

document.addEventListener('DOMContentLoaded', initializeLoginPage);
