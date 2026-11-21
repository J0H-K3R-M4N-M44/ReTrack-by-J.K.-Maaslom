<?php
// Use the same choices for each device, with unique names and labels.
$prefix = $index === 0 ? '' : 'extra_devices[' . ($index - 1) . ']';
$fieldName = static fn(string $key): string => $prefix === '' ? $key : $prefix . '[' . $key . ']';
$deviceType = $device['device_type'];
$customType = $deviceType !== '' && !in_array($deviceType, intakeDeviceTypes(), true);
?>
<fieldset class="form-wide intake-choice-group intake-device-fields" data-intake-device>
    <legend>Device <?= $index + 1 ?></legend>
    <input type="hidden" name="<?= h($fieldName('quick_choices')) ?>" value="1">
    <label>Device type
        <select name="<?= h($fieldName('device_type')) ?>" data-device-type required>
            <option value="">Choose a type</option>
            <?php foreach (intakeDeviceTypes() as $type): ?>
                <option value="<?= h($type) ?>" <?= $deviceType === $type || ($customType && $type === 'Other') ? 'selected' : '' ?>>
                    <?= h($type) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Other device name
        <input name="<?= h($fieldName('device_other')) ?>" data-device-other maxlength="80"
            value="<?= h($customType ? $deviceType : '') ?>" placeholder="Only needed for Other">
    </label>
    <?php foreach (['make' => ['Brand / make', 100], 'model' => ['Model', 160], 'serial_number' => ['Serial number (optional)', 190]] as $key => [$label, $length]): ?>
        <label><?= h($label) ?>
            <input name="<?= h($fieldName($key)) ?>" maxlength="<?= $length ?>" value="<?= h($device[$key]) ?>"
                data-device-field="<?= h($key) ?>">
        </label>
    <?php endforeach; ?>
    <?php foreach ([
        'reported_symptoms' => ['problems', 'Reported problem', intakeProblemChoices(), 2800],
        'condition_notes' => ['conditions', 'Visible damage or condition', intakeConditionChoices(), 1800],
    ] as $key => [$group, $label, $choices, $length]): ?>
        <?php $state = intakeChoiceState($device[$key], $choices); ?>
        <fieldset class="form-wide intake-device-choices" data-device-choices="<?= h($group) ?>">
            <legend><?= h($label) ?></legend>
            <div class="intake-choice-grid">
                <?php foreach ($choices as $choice): ?>
                    <label>
                        <input type="checkbox" name="<?= h($fieldName($group)) ?>[]" value="<?= h($choice) ?>"
                            <?= in_array($choice, $state['selected'], true) ? 'checked' : '' ?>>
                        <span><?= h($choice) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <label>Additional details / Other
                <textarea name="<?= h($fieldName($key)) ?>" rows="2" maxlength="<?= $length ?>"
                    placeholder="Optional unless Other is selected"><?= h($state['details']) ?></textarea>
            </label>
        </fieldset>
    <?php endforeach; ?>
    <?php if ($index > 0 && $index >= $savedDeviceCount): ?>
        <button class="secondary-btn form-wide" type="submit" name="intent" value="remove-device-<?= $index ?>">
            Remove device <?= $index + 1 ?>
        </button>
    <?php endif; ?>
</fieldset>
