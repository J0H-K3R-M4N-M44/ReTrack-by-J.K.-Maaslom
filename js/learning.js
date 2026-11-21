'use strict';

const practice = document.querySelector('#practice');
const start = document.querySelector('#practice-start');
const complete = document.querySelector('#practice-complete');
const steps = [...practice.querySelectorAll('.practice-step')];
let current = 0;

// Reset only the walkthrough. Practice never submits forms or saves repair data.
start.hidden = false;
start.addEventListener('click', () => {
  current = 0;
  complete.hidden = true;
  for (const step of steps) {
    step.hidden = true;
    step.querySelector('.practice-feedback').textContent = '';
    const form = step.querySelector('.practice-form');
    if (form) {
      form.reset();
      for (const control of form.elements) control.disabled = false;
      step.querySelector('.practice-result').hidden = true;
    }
    const hint = step.querySelector('.practice-hint');
    if (hint) hint.open = false;
    step.querySelector('.practice-next').hidden = true;
    for (const choice of step.querySelectorAll('[data-answer]')) {
      choice.removeAttribute('aria-disabled');
    }
  }
  start.textContent = 'Restart practice';
  steps[current].hidden = false;
  steps[current].querySelector('h3').focus();
});

function showFeedback(step, correct) {
  const feedback = step.querySelector('.practice-feedback');
  feedback.textContent = (correct ? 'Correct. ' : 'Try again. ') + feedback.dataset.explanation;
  if (correct) step.querySelector('.practice-next').hidden = false;
}

practice.addEventListener('submit', (event) => {
  if (!event.target.matches('.practice-form')) return;
  event.preventDefault();

  const form = event.target;
  const step = steps[current];
  if (!step?.contains(form)) return;
  const correct = [...form.querySelectorAll('[data-expected]')].every((control) => {
    const value = control.value.trim();
    if (control.inputMode === 'decimal') {
      return /^[0-9]+(?:[.][0-9]{1,2})?$/.test(value) &&
        Number(value) === Number(control.dataset.expected);
    }
    return value.toUpperCase() === control.dataset.expected.toUpperCase();
  });
  showFeedback(step, correct);
  if (correct) {
    const result = step.querySelector('.practice-result');
    result.hidden = false;
    for (const control of form.elements) control.disabled = true;
    result.focus();
  }
});

practice.addEventListener('click', (event) => {
  const step = steps[current];
  if (!step || !step.contains(event.target)) return;

  const choice = event.target.closest('[data-answer]');
  if (choice && !choice.hasAttribute('aria-disabled')) {
    const correct = choice.dataset.answer === 'correct';
    showFeedback(step, correct);
    if (correct) {
      for (const answer of step.querySelectorAll('[data-answer]')) {
        answer.setAttribute('aria-disabled', 'true');
      }
    }
  }

  if (event.target.closest('.practice-next')) {
    step.hidden = true;
    current++;
    if (current < steps.length) {
      steps[current].hidden = false;
      steps[current].querySelector('h3').focus();
    } else {
      start.textContent = 'Replay practice';
      complete.hidden = false;
      complete.focus();
    }
  }
});
