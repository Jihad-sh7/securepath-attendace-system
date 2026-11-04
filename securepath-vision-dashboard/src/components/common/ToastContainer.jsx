import { CheckCircle2, Info, X, XCircle } from "lucide-react";

const icons = {
  success: CheckCircle2,
  warning: Info,
  danger: XCircle,
  neutral: Info
};

export default function ToastContainer({ toasts, onDismiss }) {
  return (
    <div className="toast-stack" aria-live="polite" aria-atomic="true">
      {toasts.map((toast) => {
        const Icon = icons[toast.tone] || Info;
        return (
          <div className={`toast toast-${toast.tone}`} key={toast.id}>
            <Icon size={18} />
            <span>{toast.message}</span>
            <button type="button" aria-label="Dismiss notification" onClick={() => onDismiss(toast.id)}>
              <X size={16} />
            </button>
          </div>
        );
      })}
    </div>
  );
}
