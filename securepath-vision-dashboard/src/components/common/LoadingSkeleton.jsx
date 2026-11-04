export default function LoadingSkeleton({ rows = 4 }) {
  return (
    <div className="skeleton-stack" aria-label="Loading">
      {Array.from({ length: rows }).map((_, index) => (
        <span key={index} className="skeleton-line" />
      ))}
    </div>
  );
}
