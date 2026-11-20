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
