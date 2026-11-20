// Practice uses only the open dialog. Closing or replaying it discards the exercise.
const practiceSerials = ['SG-4101', 'SG-4102'];

function startPractice() {
  formModal('Practice intake · Customer', 'practice-customer',
    '<p>Two Acer Aspire laptops arrived together. Start a practice draft for Jo Reyes, whose contact ends in 2048.</p>' +
    select('Existing customer', 'person', ['Choose a customer', 'Jo Reyes · 1092', 'Jo Reyes · 2048']) +
    '<p role="status"></p>', 'Continue');
}

function practiceDevice(index) {
  formModal('Practice intake · Device ' + (index + 1), 'practice-device',
    '<p>Read the intake label: Acer Aspire · ' + practiceSerials[index] +
    '. The case is scratched and a charger is included.</p>' +
    '<input type="hidden" name="index" value="' + index + '">' +
    field('Serial number', 'serial') +
    select('Visible condition', 'condition', ['Choose condition', 'Scratched case', 'No visible damage']) +
    select('Accessories', 'accessories', ['Choose accessories', 'Charger', 'None']) +
    '<p role="status"></p>', 'Check device');
}

function checkPractice(kind, form) {
  const values = new FormData(form);
  const feedback = form.querySelector('[role="status"]');
  if (kind === 'practice-customer') {
    if (values.get('person') !== 'Jo Reyes · 2048') {
      feedback.textContent = 'Compare the contact suffix. Both customers have the same name.';
      return;
    }
    practiceDevice(0);
  } else if (kind === 'practice-device') {
    const index = Number(values.get('index'));
    if (values.get('serial').trim().toUpperCase() !== practiceSerials[index]) {
      feedback.textContent = 'Check the serial printed on this laptop’s label.';
      return;
    }
    if (values.get('condition') !== 'Scratched case' || values.get('accessories') !== 'Charger') {
      feedback.textContent = 'Record the scratch and the charger before continuing.';
      return;
    }
    if (index === 0) practiceDevice(1);
    else formModal('Practice intake · Review', 'practice-review',
      '<p>Jo Reyes · contact ending 2048</p><ol>' +
      practiceSerials.map(serial => '<li>Acer Aspire · ' + serial + ' · scratched case · charger</li>').join('') +
      '</ol><label><input type="checkbox" required> I checked both serials with the customer.</label>', 'Save practice draft');
  } else {
    modal('Practice draft complete', '<p>You matched the customer and recorded two separate devices. In the cash desk, a saved draft still needs review and issuance before tracking is available.</p>' +
      btn('Practice again', 'practice-start') + btn('Done', 'close'));
  }
}

// Lessons use fictional task cards. Each step stays in the dialog and never calls a shop action.
const roleLessons = {
  Customer: {
    title: 'Track a repair and review a quote',
    steps: [
      { prompt: 'Your practice receipt shows tracking code DEMO-1042. Enter it to find the repair.', label: 'Tracking code', answer: 'DEMO-1042', hint: 'Copy the tracking code from the receipt.' },
      { prompt: 'Open Costs. Your receipt lists 2468 as the demo PIN.', label: 'Demo PIN', answer: '2468', hint: 'The four digits are separate from the tracking code.' },
      { prompt: 'The repair team quoted ₱650 for cleaning. You have reviewed the amount and want the work to proceed.', label: 'Quote decision', choices: ['Decline', 'Approve quote'], answer: 'Approve quote', hint: 'Approving gives the technician permission to proceed.' }
    ],
    finish: 'You approved the practice estimate. In your portal, Costs holds quote decisions and Messages lets you ask the repair team a question first.'
  },
  Technician: {
    title: 'Record a test and update a repair',
    steps: [
      { prompt: 'After the repair, the laptop still shuts down during a load test. Record what happened.', label: 'Test outcome', choices: ['Passed', 'Failed'], answer: 'Failed', note: 'Tests performed and results', hint: 'A shutdown is a failed test. Record the observed result.' },
      { prompt: 'The failed test returned the device to Repairing. After fixing the cooling fan, the laptop runs the same load test successfully. Record the retest.', label: 'Test outcome', choices: ['Passed', 'Failed'], answer: 'Passed', note: 'Retest notes', hint: 'Record the successful retest before marking the device ready.' },
      { prompt: 'All approved work is complete and the retest passed. Update the repair board.', label: 'Repair status', choices: ['Awaiting parts', 'Ready for pickup', 'Received'], answer: 'Ready for pickup', hint: 'The device has passed testing and can now await collection.' }
    ],
    finish: 'The practice device is ready for pickup. Handing it back to the customer is a separate cash-desk action.'
  },
  Collector: {
    title: 'Collect a device and record custody',
    steps: [
      { prompt: 'Taylor has two pickups at Central Branch. Choose the one whose meeting point, schedule and fee have been confirmed.', label: 'Pickup', choices: ['PU-301 · Requested', 'PU-302 · Confirmed'], answer: 'PU-302 · Confirmed', hint: 'A requested pickup still needs confirmation.' },
      { prompt: 'At pickup, the customer hands you a scratched laptop and a charger. The handoff slip reads HAND-302. Enter the reference and describe what you received.', label: 'Handoff reference', answer: 'HAND-302', note: 'Device condition and accessories', hint: 'Use the reference on the slip and record what came with the laptop.' },
      { prompt: 'You have the device and are travelling to the branch. Which status describes custody now?', label: 'Pickup status', choices: ['Received at branch', 'Collected'], answer: 'Collected', hint: 'Branch receipt happens after the device reaches the counter.' }
    ],
    finish: 'Collection is recorded in the exercise. At the branch, open the collected pickup and use Receive at branch to begin intake.'
  },
  Admin: {
    title: 'Assign a technician and reserve a part',
    steps: [
      { prompt: 'A new ticket belongs to Central Branch. Mika is active at Central. Sam is active at North. Choose the technician for this ticket.', label: 'Assign technician', choices: ['Sam · North', 'Mika · Central'], answer: 'Mika · Central', hint: 'Choose an active technician at the ticket’s branch.' },
      { prompt: 'Mika requests one SSD. Central has one available. Approve the request before the technician installs it.', label: 'Inventory action', choices: ['Record usage', 'Reserve one SSD'], answer: 'Reserve one SSD', hint: 'Reserve stock at approval. Record usage when the part is used.' },
      { prompt: 'Central started with one available SSD and reserved one. Enter the quantity still available for another ticket.', label: 'Available quantity', answer: '0', hint: 'The reserved SSD is already committed to this repair.' }
    ],
    finish: 'The practice ticket has a technician and a reserved part. The reservation and actual usage are separate inventory actions.'
  }
};

function lessonButton(name) {
  if (name === 'Cashier') return btn('Practice cashier intake', 'practice-start');
  return btn(roleLessons[name].title, 'role-lesson', `data-lesson="${name}"`);
}

function lessonMenu(current) {
  const names = ['Customer', 'Cashier', 'Technician', 'Collector', 'Admin'];
  const recommended = names.includes(current) ? current : 'Customer';
  return '<p>Practice with sample records. Close a lesson at any time or replay it after finishing.</p>' +
    lessonButton(recommended) + '<details class="section"><summary>Other role lessons</summary><div class="stack">' +
    names.filter(name => name !== recommended).map(name => lessonButton(name)).join('') + '</div></details>';
}

function showRoleStep(name, index) {
  const lesson = roleLessons[name];
  const step = lesson.steps[index];
  if (!step) {
    modal('Practice complete', '<p>' + esc(lesson.finish) + '</p>' +
      btn('Practice again', 'role-lesson', `data-lesson="${name}"`) + btn('Done', 'close'));
    return;
  }
  const control = step.choices ? select(step.label, 'answer', ['Choose an option', ...step.choices]) : field(step.label, 'answer');
  formModal(lesson.title, 'practice-role',
    `<p>Step ${index + 1} of ${lesson.steps.length} · ${name}</p><p>${esc(step.prompt)}</p>` +
    control + (step.note ? area(step.note, 'notes') : '') +
    `<input type="hidden" name="lesson" value="${name}"><input type="hidden" name="step" value="${index}"><p role="status"></p>`,
    index === lesson.steps.length - 1 ? 'Finish practice' : 'Check and continue');
  document.querySelector('dialog [name="answer"]').focus();
}

function checkRoleStep(form) {
  const values = new FormData(form);
  const name = values.get('lesson');
  const index = Number(values.get('step'));
  const step = roleLessons[name].steps[index];
  const feedback = form.querySelector('[role="status"]');
  if (values.get('answer').trim().toUpperCase() !== step.answer.toUpperCase()) {
    feedback.textContent = step.hint;
    return;
  }
  if (step.note && !values.get('notes').trim()) {
    feedback.textContent = 'Write what you observed before continuing.';
    return;
  }
  showRoleStep(name, index + 1);
}
