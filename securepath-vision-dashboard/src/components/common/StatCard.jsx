export default function StatCard({ label, value, delta, icon, tone = "neutral", footer }) {
  return (
    <article className={`stat-card stat-${tone}`}>
      <div className="stat-top">
        <span className="stat-icon">{icon}</span>
        {delta && <span className="stat-delta">{delta}</span>}
      </div>
      <strong>{value}</strong>
      <span>{label}</span>
      {footer && <small>{footer}</small>}
    </article>
  );
}
