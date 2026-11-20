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
    step.querySelector('.practice-next').hidden = true;
    for (const choice of step.querySelectorAll('[data-answer]')) {
      choice.removeAttribute('aria-disabled');
    }
  }
  start.textContent = 'Restart practice';
  steps[current].hidden = false;
  steps[current].querySelector('h3').focus();
});

practice.addEventListener('click', (event) => {
  const step = steps[current];
  if (!step || !step.contains(event.target)) return;

  const choice = event.target.closest('[data-answer]');
  if (choice && !choice.hasAttribute('aria-disabled')) {
    const feedback = step.querySelector('.practice-feedback');
    const correct = choice.dataset.answer === 'correct';
    feedback.textContent = (correct ? 'Correct. ' : 'Try again. ') + feedback.dataset.explanation;
    if (correct) {
      for (const answer of step.querySelectorAll('[data-answer]')) {
        answer.setAttribute('aria-disabled', 'true');
      }
      step.querySelector('.practice-next').hidden = false;
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
