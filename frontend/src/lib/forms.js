// Shape every server action returns to its form: { message, errors, values }
export function formError(data, values = {}) {
  const errors = data.errors ?? {};

  // Field errors are shown under the fields; the top message only when there are none
  return { message: Object.keys(errors).length ? null : data.message, errors, values };
}

// FormData -> plain object; empty strings become null so optional fields can be cleared
export function formValues(formData) {
  const values = {};

  for (const [key, value] of formData.entries()) {
    if (!key.startsWith('$')) values[key] = value === '' ? null : value;
  }

  return values;
}

// Role form: name + ticked permissions (an empty list is valid)
export function roleValues(formData) {
  return { name: formData.get('name'), permissions: formData.getAll('permissions') };
}
