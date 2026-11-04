export default function PageHeader({ title, eyebrow, description, actions, meta }) {
  return (
    <section className="page-header">
      <div>
        {eyebrow && <p className="eyebrow">{eyebrow}</p>}
        <h1>{title}</h1>
        {description && <p>{description}</p>}
      </div>
      <div className="page-header-actions">
        {meta}
        {actions}
      </div>
    </section>
  );
}
