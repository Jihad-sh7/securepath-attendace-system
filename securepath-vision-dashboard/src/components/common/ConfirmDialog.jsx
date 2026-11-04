import { AlertTriangle } from "lucide-react";
import Modal from "./Modal.jsx";

export default function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel = "Confirm",
  onConfirm,
  onClose,
  danger
}) {
  return (
    <Modal
      open={open}
      title={title}
      onClose={onClose}
      size="sm"
      footer={
        <>
          <button className="button button-ghost" type="button" onClick={onClose}>
            Cancel
          </button>
          <button
            className={`button ${danger ? "button-danger" : "button-primary"}`}
            type="button"
            onClick={onConfirm}
          >
            {confirmLabel}
          </button>
        </>
      }
    >
      <div className="confirm-copy">
        <AlertTriangle size={24} />
        <p>{message}</p>
      </div>
    </Modal>
  );
}
