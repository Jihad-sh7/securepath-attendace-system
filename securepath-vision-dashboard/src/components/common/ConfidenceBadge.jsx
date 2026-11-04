export default function ConfidenceBadge({ value }) {
  const tone = value >= 90 ? "success" : value >= 70 ? "warning" : "danger";
  return (
    <span className={`confidence confidence-${tone}`} title={`Confidence ${value}%`}>
      <span className="confidence-track">
        <span style={{ width: `${Math.max(4, value)}%` }} />
      </span>
      <strong>{Number(value).toFixed(1)}%</strong>
    </span>
  );
}
