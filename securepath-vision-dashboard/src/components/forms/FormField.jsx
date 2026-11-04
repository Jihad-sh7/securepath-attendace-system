export default function FormField({
  label,
  name,
  error,
  children,
  hint,
  required,
  className = ""
}) {
  return (
    <label className={`field ${className}`}>
      <span>
        {label}
        {required && <em>*</em>}
      </span>
      {children || <input name={name} aria-invalid={Boolean(error)} required={required} />}
      {hint && <small>{hint}</small>}
      {error && <strong role="alert">{error}</strong>}
    </label>
  );
}
