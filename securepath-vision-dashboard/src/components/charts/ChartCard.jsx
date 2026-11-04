export default function ChartCard({ title, action, children, className = "" }) {
  return (
    <section className={`chart-card ${className}`}>
      <header>
        <h2>{title}</h2>
        {action}
      </header>
      <div className="chart-body">{children}</div>
    </section>
  );
}
